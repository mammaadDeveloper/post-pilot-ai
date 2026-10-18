<script setup lang="ts">
import { useThemeColor, themeColorPresets } from '@/composables/useThemeColor';

const { themeColor, inputColor, storageError, updateThemeColor } =
    useThemeColor();
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5"
        aria-labelledby="theme-color-heading"
    >
        <div class="flex flex-col gap-1">
            <h2 id="theme-color-heading" class="text-base font-semibold">
                Theme color
            </h2>
            <p class="text-sm leading-5 text-muted-foreground">
                Choose an accent for buttons, links, focus states, and active
                workspace navigation.
            </p>
        </div>

        <fieldset class="grid gap-3">
            <legend class="mb-2 text-sm font-medium">Color presets</legend>
            <div class="flex flex-wrap gap-3">
                <button
                    v-for="preset in themeColorPresets"
                    :key="preset.value"
                    type="button"
                    class="group flex min-h-11 items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Use ${preset.name} theme color`"
                    :aria-pressed="
                        (themeColor ?? themeColorPresets[0].value) ===
                        preset.value
                    "
                    @click="updateThemeColor(preset.value)"
                >
                    <span
                        class="size-5 rounded-full ring-1 ring-black/10 ring-inset"
                        :style="{ backgroundColor: preset.value }"
                        aria-hidden="true"
                    ></span>
                    {{ preset.name }}
                </button>
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-3">
            <label
                for="custom-theme-color"
                class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-border px-3 text-sm font-medium focus-within:ring-2 focus-within:ring-ring hover:bg-muted"
            >
                <input
                    id="custom-theme-color"
                    type="color"
                    :value="inputColor"
                    class="size-7 cursor-pointer rounded border-0 bg-transparent p-0"
                    aria-describedby="theme-color-help"
                    @input="
                        updateThemeColor(
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                Custom color
            </label>
            <button
                type="button"
                class="min-h-11 rounded-lg px-3 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:text-muted-foreground disabled:no-underline"
                :disabled="themeColor === null"
                @click="updateThemeColor(null)"
            >
                Use default
            </button>
            <span
                class="inline-flex items-center gap-2 text-xs text-muted-foreground"
                aria-live="polite"
            >
                <span
                    class="size-4 rounded-full ring-1 ring-black/10 ring-inset"
                    :style="{ backgroundColor: inputColor }"
                    aria-hidden="true"
                ></span>
                {{ themeColor ? inputColor.toUpperCase() : 'Current default' }}
            </span>
        </div>
        <p
            id="theme-color-help"
            class="text-xs leading-5 text-muted-foreground"
        >
            Your selection is saved in this browser. Text color is adjusted
            automatically for contrast; error and status colors are unchanged.
        </p>
        <p v-if="storageError" class="text-sm text-destructive" role="alert">
            {{ storageError }}
        </p>
    </section>
</template>
