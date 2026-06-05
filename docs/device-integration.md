# Device Integration — e-Hajiri

> The biometric device is **never** the source of truth. It is a *signal generator* — it produces attendance events that the application stores, deduplicates, and uses to compute summaries. This document defines the abstraction that keeps the application independent of any single vendor.

---

## 1. Core Invariants

These rules govern every line of device-integration code. Violating them creates the kind of bug that survives architectural rewrites.

1. **The application database is the source of truth.** Devices generate events; the application owns them after ingest.
2. **All raw events are stored permanently.** Never delete a punch. Corrections append a new event; they do not edit history.
3. **The application never depends on a vendor SDK class directly.** Every consumer talks to `AttendanceDeviceInterface`.
4. **Employee identity is mapped, not assumed.** `employee_id` ≠ `device_user_id`. Mappings live in `employee_device_mappings`.
5. **Sync is idempotent.** Re-running a sync after a partial failure produces the same database state, never duplicates.
6. **No biometric template or image is ever stored on the application server.** Templates remain on the device.
7. **Time is reconciled, not trusted.** Device timestamps are normalized to UTC against a known device timezone; clock drift is monitored.

---

## 2. The Device Abstraction Layer

### 2.1 Why an interface

A `ZKTecoDevice` class scattered through controllers and jobs would mean:
- Replacing ZKTeco with Hikvision becomes a rewrite.
- Testing requires a physical device.
- A vendor SDK change ripples through unrelated modules.

Instead, every consumer (sync job, provisioning command, health monitor) talks to `AttendanceDeviceInterface`. Concrete vendor classes are constructed by `DeviceConnectorFactory` from `devices.vendor`. This makes vendor swap a one-line factory addition.

### 2.2 Where it lives

```
app/Domain/Device/
├── Contracts/
│   ├── AttendanceDeviceInterface.php
│   └── DeviceConnectorFactoryInterface.php
├── DataTransferObjects/
│   ├── DeviceInfoDto.php
│   ├── DeviceUserDto.php
│   ├── AttendanceLogDto.php
│   └── SyncResultDto.php
├── Drivers/
│   ├── ZKTeco/
│   │   ├── ZKTecoDevice.php
│   │   ├── ZKTecoConnection.php
│   │   └── ZKTecoLogMapper.php
│   ├── Hikvision/
│   │   └── HikvisionDevice.php    (Phase 2)
│   └── Fake/
│       └── FakeDevice.php          (for tests + local dev)
├── Exceptions/
│   ├── DeviceConnectionException.php
│   ├── DeviceSyncException.php
│   └── UnsupportedDeviceVendorException.php
├── Factories/
│   └── DeviceConnectorFactory.php
└── Services/
    ├── DeviceSyncService.php
    └── DeviceHealthService.php
```

### 2.3 The interface

```php
<?php

namespace App\Domain\Device\Contracts;

use App\Domain\Device\DataTransferObjects\AttendanceLogDto;
use App\Domain\Device\DataTransferObjects\DeviceInfoDto;
use App\Domain\Device\DataTransferObjects\DeviceUserDto;
use App\Domain\Device\DataTransferObjects\SyncResultDto;
use App\Domain\Device\Models\Device;
use DateTimeImmutable;

interface AttendanceDeviceInterface
{
    /**
     * Bind this driver to a configured device row.
     */
    public function for(Device $device): self;

    /**
     * Open the connection (TCP, HTTP, SDK handle, etc.).
     * Idempotent — repeated calls are no-ops while connected.
     */
    public function connect(): void;

    /**
     * Close the connection. Safe to call without connect().
     */
    public function disconnect(): void;

    /**
     * Lightweight liveness probe.
     */
    public function ping(): bool;

    /**
     * Identity + firmware + capabilities of the physical device.
     */
    public function getDeviceInfo(): DeviceInfoDto;

    /**
     * Fetch attendance logs.
     *
     * @param DateTimeImmutable|null $since  Use null for full sync; otherwise incremental.
     * @return iterable<AttendanceLogDto>     Generator preferred for large fleets.
     */
    public function getLogs(?DateTimeImmutable $since = null): iterable;

    /**
     * Pull logs and hand them to the ingest pipeline.
     * Returns a SyncResultDto with counts, cursor, and any soft errors.
     */
    public function syncLogs(): SyncResultDto;

    /**
     * Push an employee record onto the device.
     * Vendor may require fingerprint enrollment separately on the device UI.
     */
    public function createUser(DeviceUserDto $user): void;

    /**
     * Update an existing device user (name, card, privilege).
     */
    public function updateUser(DeviceUserDto $user): void;

    /**
     * Remove a device user. Templates on the device are destroyed.
     */
    public function deleteUser(string $deviceUserId): void;

    /**
     * Push all active employees (mapped to this device) to the device.
     * Reconciles: creates missing, updates changed, deletes unmapped.
     */
    public function syncEmployees(): SyncResultDto;
}
```

### 2.4 Why each method exists

| Method | Reason |
|---|---|
| `for(Device)` | Binds the driver to a device row before any operation. Lets the factory return a singleton driver re-used across devices. |
| `connect / disconnect` | Vendor SDKs are connection-oriented; explicit lifecycle is testable and works with `try/finally`. |
| `ping` | Health monitor needs a cheap probe distinct from a full info fetch. |
| `getDeviceInfo` | Required for capability detection (does this firmware support push? face? card?). |
| `getLogs(since)` | Read path. Generator return so 100k-record pulls don't blow memory. |
| `syncLogs` | Convenience wrapper that pulls + ingests + advances cursor in one idempotent call. |
| `createUser / updateUser / deleteUser` | Provisioning path. Used by `syncEmployees` and by manual operator actions. |
| `syncEmployees` | Reconciliation. Source-of-truth direction is *app → device*. |

### 2.5 DTOs (sketches)

```php
final readonly class DeviceInfoDto {
    public function __construct(
        public string $vendor,
        public string $model,
        public string $serial,
        public string $firmware,
        public string $deviceTimezone,
        public DateTimeImmutable $deviceTime,
        public int $userCount,
        public int $logCount,
        public array $capabilities,   // ['fingerprint', 'face', 'card', 'push']
    ) {}
}

final readonly class AttendanceLogDto {
    public function __construct(
        public string $deviceUserId,
        public DateTimeImmutable $timestamp,    // already UTC
        public string $verificationMethod,      // fingerprint|face|card|password
        public ?int $deviceLogId,               // vendor cursor, nullable for push events
        public array $raw,                      // verbatim payload
    ) {}
}

final readonly class DeviceUserDto {
    public function __construct(
        public string $deviceUserId,
        public string $name,
        public ?string $cardNumber,
        public string $privilege,               // user|admin
        public bool $isActive,
    ) {}
}

final readonly class SyncResultDto {
    public function __construct(
        public int $fetched,
        public int $ingested,
        public int $duplicates,
        public int $orphans,
        public ?int $newCursor,
        public array $errors,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $finishedAt,
    ) {}
}
```

### 2.6 The factory

```php
final class DeviceConnectorFactory implements DeviceConnectorFactoryInterface
{
    /** @var array<string, class-string<AttendanceDeviceInterface>> */
    private array $drivers = [
        'zkteco'    => ZKTecoDevice::class,
        // 'hikvision' => HikvisionDevice::class,  // Phase 2
        'fake'      => FakeDevice::class,
    ];

    public function for(Device $device): AttendanceDeviceInterface
    {
        $vendor = strtolower($device->vendor);

        if (! isset($this->drivers[$vendor])) {
            throw new UnsupportedDeviceVendorException($vendor);
        }

        return app($this->drivers[$vendor])->for($device);
    }
}
```

Registered in a service provider; consumers inject `DeviceConnectorFactoryInterface`.

---

## 3. ZKTeco Driver Notes

### Protocol options
- **TCP/UDP (UDP 4370 default)** — the standard SDK. We use the `rats/zkteco` PHP library or a maintained fork; if none is healthy at implementation time, we'll wrap the low-level protocol directly.
- **Push SDK / Cloud** — newer ZKTeco devices push events to a configurable endpoint. Phase 2.

### Quirks the driver must handle
- Device time may drift; ingest normalizes via device-reported timezone + clock-drift correction.
- Some firmware returns no log ID — we then dedupe on `(device_id, device_user_id, timestamp, verification_method)`.
- Partial fetches: if the connection drops mid-pull, the cursor is **not** advanced. Next run re-pulls from the previous cursor.
- Unmapped `device_user_id` produces an *orphan event* — stored with `employee_id = null` and surfaced to operators for manual mapping.

---

## 4. Sync Workflow

```
Scheduler (every 5 min)
   │
   ▼
SyncDeviceLogsJob(device_id)
   │
   ├── factory.for(device)             ← AttendanceDeviceInterface
   ├── connect()
   ├── getLogs(since = device.last_log_cursor)
   ├── IngestAttendanceEventsJob       (chained, batched)
   │      │
   │      ├── dedupe on natural key
   │      ├── insert into attendance_events
   │      ├── flag orphans
   │      └── advance device cursor on success
   │
   └── GenerateAttendanceSummariesJob  (for affected employee/date pairs)
```

### Idempotency guarantees
- **At the wire**: `getLogs(since)` is pure; retrying re-reads the same range.
- **At ingest**: unique index `(device_id, device_user_id, event_timestamp, verification_method)` on `attendance_events` makes duplicate inserts a no-op.
- **At cursor**: `devices.last_log_id` advances only after the ingest transaction commits.

### Backoff and failure
- Transient failures (network, EOF mid-pull) → exponential backoff, max 5 retries, then alert.
- Permanent failures (auth changed, device replaced) → mark device `status = error`, surface in admin UI.

---

## 5. Employee Provisioning

When an employee is added to an organization:

1. Operator maps the employee to one or more devices, choosing a `device_user_id` per device.
2. `syncEmployees()` on each device pushes `DeviceUserDto` for that employee.
3. The fingerprint/face enrollment happens at the physical device (out of scope for the application).
4. If an employee is deactivated, the next `syncEmployees()` removes them from the device.

> The application never holds biometric templates. The device holds them. If a device is replaced, the new device must be re-enrolled — that is a deliberate trade-off of the no-template-storage policy.

---

## 6. Clock Drift and Health Monitoring

### What we record
- `devices.device_time` — most recent device-reported time from `getDeviceInfo()`.
- `devices.server_time` — server time at the moment we asked.
- `devices.last_online_at` — last successful `ping()` or sync.
- `devices.last_sync_at` — last successful log ingest.

### What we alert on
- **Drift > 60 seconds** (configurable): warn. Drift > 5 minutes: critical.
- **No ping for > 15 minutes** (configurable): device-offline alert.
- **No new logs for > N hours during work hours**: silent-device alert (could be empty office, but worth checking).

### Why we still trust ingested timestamps when drift is small
- Punches are correlated to office windows that have minutes of grace already. Sub-minute drift is below our resolution.
- For drift above the warn threshold, we record `device_time` and `server_time` on every event in the `raw_payload`, so future re-processing can shift timestamps without losing the original.

---

## 7. Multi-Device per Organization

- An organization may have N devices across M locations.
- An employee may map to multiple devices (multi-office staff).
- Each device runs its own sync schedule.
- Events from any of an employee's devices on the same `work_date` are merged in summary generation.

> Example: A ward officer punches in at the ward office (device A) at 9:00 and punches out at the main municipality office (device B) at 17:00. The summary for that date reflects both.

---

## 8. Adding a New Vendor (the contract test)

When a new vendor is added — say, ESSL — the steps are:

1. Implement `AttendanceDeviceInterface` in `app/Domain/Device/Drivers/Essl/EsslDevice.php`.
2. Register in `DeviceConnectorFactory::$drivers`.
3. Pass the **driver contract test suite** in `tests/Feature/Device/Contracts/AttendanceDeviceContractTest.php`.

The contract test suite is vendor-independent: it runs against any class implementing `AttendanceDeviceInterface` and verifies:
- `connect/disconnect` are idempotent.
- `getLogs(null)` returns a non-null iterable.
- `getLogs(since)` returns no records earlier than `since`.
- `syncLogs` advances the cursor only on success.
- `createUser` then `getDeviceInfo().userCount` increases by one.
- `deleteUser` then re-`createUser` works.
- DTOs returned conform to their type contracts.

The `FakeDevice` driver passes the same suite — that's how we validate the contract itself.

---

## 9. Mobile, GPS, and Web Punches (Future)

The same `AttendanceLogDto` is the unit of ingestion. A mobile punch is just an `AttendanceLogDto` with `verification_method = 'mobile'`. It enters the system through a dedicated endpoint (`POST /api/v1/mobile/punches`), not through `AttendanceDeviceInterface`, because the *transport* is different — but the *destination* (the same ingest pipeline) is identical.

This is why the interface only covers what physical biometric devices need. Software sources of events use a parallel path that converges at ingest.

---

## 10. Testing the Layer

- Every driver: unit-tested with mocked transports.
- Every driver: passes the contract test suite.
- The ingest pipeline: integration-tested with `FakeDevice` end-to-end (sync job → ingest → summary).
- Health monitor: tested with time-travel (`Carbon::setTestNow`) for drift and stale-device scenarios.
- No test ever touches a real device — that's a manual smoke step before each release.
