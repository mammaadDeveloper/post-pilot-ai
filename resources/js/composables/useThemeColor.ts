import { computed, ref } from 'vue';

export const defaultThemeColor = '#3d000e';
export const themeColorPresets = [
    { name: 'PostPilot Burgundy', value: defaultThemeColor },
    { name: 'Ocean Blue', value: '#155eef' },
    { name: 'Forest Green', value: '#087443' },
    { name: 'Warm Terracotta', value: '#b5472f' },
    { name: 'Royal Violet', value: '#6941c6' },
    { name: 'Graphite', value: '#475467' },
] as const;

const storageKey = 'postpilot.theme-color';
const cssProperties = [
    '--primary',
    '--primary-foreground',
    '--ring',
    '--sidebar-primary',
    '--sidebar-primary-foreground',
    '--sidebar-ring',
    '--chart-1',
] as const;

const themeColor = ref<string | null>(null);
const storageError = ref<string | null>(null);
const inputColor = computed(() => themeColor.value ?? defaultThemeColor);

function isHexColor(value: string): boolean {
    return /^#[\da-f]{6}$/i.test(value);
}

function foregroundFor(hexColor: string): string {
    const channels = hexColor
        .slice(1)
        .match(/.{2}/g)
        ?.map((channel) => Number.parseInt(channel, 16) / 255);

    if (!channels || channels.length !== 3) {
        return '#ffffff';
    }

    const [red, green, blue] = channels.map((channel) =>
        channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4,
    );
    const luminance = 0.2126 * red + 0.7152 * green + 0.0722 * blue;

    return luminance > 0.179 ? '#171717' : '#ffffff';
}

function applyThemeColor(value: string | null): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (!value) {
        for (const property of cssProperties) {
            document.documentElement.style.removeProperty(property);
        }

        document.documentElement.removeAttribute('data-custom-theme-color');

        return;
    }

    document.documentElement.setAttribute('data-custom-theme-color', 'true');
    const foreground = foregroundFor(value);
    const root = document.documentElement.style;

    root.setProperty('--primary', value);
    root.setProperty('--primary-foreground', foreground);
    root.setProperty('--ring', value);
    root.setProperty('--sidebar-primary', value);
    root.setProperty('--sidebar-primary-foreground', foreground);
    root.setProperty('--sidebar-ring', value);
    root.setProperty('--chart-1', value);
}

export function initializeThemeColor(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const savedColor = window.localStorage.getItem(storageKey);

        if (savedColor && isHexColor(savedColor)) {
            themeColor.value = savedColor.toLowerCase();
            applyThemeColor(themeColor.value);
        } else if (savedColor) {
            window.localStorage.removeItem(storageKey);
        }

        storageError.value = null;
    } catch {
        storageError.value =
            'The saved theme color could not be read from this browser.';
    }
}

export function useThemeColor() {
    function updateThemeColor(value: string | null): void {
        if (value !== null && !isHexColor(value)) {
            storageError.value = 'Choose a valid six-digit hex color.';
            return;
        }

        const normalized = value?.toLowerCase() ?? null;
        themeColor.value = normalized;
        applyThemeColor(normalized);

        try {
            if (normalized) {
                window.localStorage.setItem(storageKey, normalized);
            } else {
                window.localStorage.removeItem(storageKey);
            }

            storageError.value = null;
        } catch {
            storageError.value =
                'The color is active for this page, but could not be saved in this browser.';
        }
    }

    return {
        themeColor,
        inputColor,
        storageError,
        updateThemeColor,
    };
}
