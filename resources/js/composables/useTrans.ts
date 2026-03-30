import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type TranslationDictionary = Record<string, unknown>;

function getNestedValue(dictionary: TranslationDictionary, key: string): unknown {
    return key.split('.').reduce<unknown>((acc, part) => {
        if (acc && typeof acc === 'object' && part in (acc as Record<string, unknown>)) {
            return (acc as Record<string, unknown>)[part];
        }

        return undefined;
    }, dictionary);
}

function interpolate(text: string, params: Record<string, string | number>): string {
    return text.replace(/:([a-zA-Z0-9_]+)/g, (_match, key: string) => {
        return params[key] !== undefined ? String(params[key]) : `:${key}`;
    });
}

export function useTrans() {
    const page = usePage<any>();

    const locale = computed(() => page.props.locale ?? 'en');
    const direction = computed(() => page.props.direction ?? 'ltr');
    const translations = computed<TranslationDictionary>(() => page.props.translations ?? {});

    const t = (key: string, params: Record<string, string | number> = {}): string => {
        const value = getNestedValue(translations.value, key);
        if (typeof value !== 'string') {
            return key;
        }

        return interpolate(value, params);
    };

    const raw = <T = unknown>(key: string, fallback: T): T => {
        const value = getNestedValue(translations.value, key);
        return value === undefined ? fallback : (value as T);
    };

    return {
        t,
        raw,
        locale,
        direction,
    };
}
