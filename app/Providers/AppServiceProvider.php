<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Concerns\Auditable;
use App\Support\Eloquent\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Device connector factory binding lives in DeviceServiceProvider.
    }

    public function boot(): void
    {
        $this->registerAuditObservers();
    }

    /**
     * Attach the AuditObserver to every Eloquent model that uses the
     * Auditable trait. Done centrally to avoid model-boot recursion.
     */
    private function registerAuditObservers(): void
    {
        $domainPath = app_path('Domain');
        if (! is_dir($domainPath)) {
            return;
        }

        $modelDirs = Finder::create()->in($domainPath)->directories()->name('Models');
        foreach ($modelDirs as $dir) {
            foreach (Finder::create()->in($dir->getPathname())->files()->name('*.php') as $file) {
                $class = $this->classFromFile($file->getPathname());
                if ($class === null || ! class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                if (! $reflection->isSubclassOf(Model::class)) {
                    continue;
                }

                if (in_array(Auditable::class, $this->classUses($class), true)) {
                    $class::observe(AuditObserver::class);
                }
            }
        }
    }

    private function classFromFile(string $path): ?string
    {
        $contents = (string) file_get_contents($path);
        if (! preg_match('/namespace\s+([^;]+);/', $contents, $ns)) {
            return null;
        }
        if (! preg_match('/class\s+(\w+)/', $contents, $cl)) {
            return null;
        }
        return trim($ns[1]) . '\\' . $cl[1];
    }

    /** @return array<int,string> */
    private function classUses(string $class): array
    {
        $traits = [];
        do {
            $traits = array_merge(class_uses($class) ?: [], $traits);
        } while ($class = get_parent_class($class));

        $result = $traits;
        foreach ($traits as $trait) {
            $result = array_merge(class_uses($trait) ?: [], $result);
        }
        return array_values(array_unique($result));
    }
}
