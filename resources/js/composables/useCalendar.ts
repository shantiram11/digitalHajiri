/**
 * Bridge to the backend's CalendarService.
 *
 * For MVP, all dates from the API are AD ISO strings. This composable wraps
 * display so swapping AD ↔ BS once the conversion table is in place becomes
 * a one-line change in this file.
 */
import { computed, ref } from 'vue';

export type CalendarMode = 'AD' | 'BS';

const mode = ref<CalendarMode>((localStorage.getItem('calendar.mode') as CalendarMode) ?? 'AD');

export function useCalendar() {
    function setMode(next: CalendarMode): void {
        mode.value = next;
        localStorage.setItem('calendar.mode', next);
    }

    function format(adIso: string | null | undefined): string {
        if (!adIso) return '';
        if (mode.value === 'AD') {
            return new Date(adIso).toLocaleDateString();
        }
        // TODO(phase-4): plug in AD→BS conversion table.
        return `BS pending (${new Date(adIso).toLocaleDateString()})`;
    }

    return {
        mode: computed(() => mode.value),
        setMode,
        format,
    };
}
