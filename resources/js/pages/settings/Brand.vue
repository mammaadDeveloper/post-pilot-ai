<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { update as updateBrandProfile } from '@/actions/App/Http/Controllers/Settings/BrandProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/brand';

type BrandProfile = {
    brand_name: string;
    description: string | null;
    industry: string | null;
    target_audience: string | null;
    preferred_tone: string | null;
    preferred_languages: string[];
    content_guidelines: string | null;
    avoid_terms: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Brand profile',
                href: edit(),
            },
        ],
    },
});

defineProps<{
    brandProfile: BrandProfile | null;
}>();

const languages = ['English', 'Persian'] as const;
</script>

<template>
    <Head title="Brand profile" />

    <h1 class="sr-only">Brand profile</h1>

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="Brand profile"
            description="Set the brand context that PostPilot includes with your AI writing requests."
        />

        <Form
            v-bind="updateBrandProfile.form()"
            class="flex flex-col gap-5"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="brand-name">Brand name</Label>
                <Input
                    id="brand-name"
                    name="brand_name"
                    :default-value="brandProfile?.brand_name"
                    required
                    maxlength="255"
                    autocomplete="organization"
                />
                <InputError :message="errors.brand_name" />
            </div>

            <div class="grid gap-2">
                <Label for="brand-description">Description</Label>
                <textarea
                    id="brand-description"
                    name="description"
                    :value="brandProfile?.description ?? ''"
                    maxlength="3000"
                    rows="4"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="What does your brand offer and what makes it distinct?"
                ></textarea>
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="brand-industry">Industry or category</Label>
                    <Input
                        id="brand-industry"
                        name="industry"
                        :default-value="brandProfile?.industry ?? ''"
                        maxlength="120"
                    />
                    <InputError :message="errors.industry" />
                </div>
                <div class="grid gap-2">
                    <Label for="brand-audience">Target audience</Label>
                    <Input
                        id="brand-audience"
                        name="target_audience"
                        :default-value="brandProfile?.target_audience ?? ''"
                        maxlength="500"
                    />
                    <InputError :message="errors.target_audience" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="brand-tone">Preferred tone of voice</Label>
                <Input
                    id="brand-tone"
                    name="preferred_tone"
                    :default-value="brandProfile?.preferred_tone ?? ''"
                    maxlength="100"
                    placeholder="For example, friendly and knowledgeable"
                />
                <InputError :message="errors.preferred_tone" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Content languages</legend>
                <div class="flex flex-wrap gap-4">
                    <label
                        v-for="language in languages"
                        :key="language"
                        class="inline-flex min-h-10 items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="preferred_languages[]"
                            :value="language"
                            :checked="
                                brandProfile?.preferred_languages?.includes(
                                    language,
                                ) ?? language === 'English'
                            "
                            class="size-4 rounded border-input accent-primary"
                        />
                        {{ language }}
                    </label>
                </div>
                <InputError :message="errors.preferred_languages" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="brand-guidelines">Content guidelines</Label>
                <textarea
                    id="brand-guidelines"
                    name="content_guidelines"
                    :value="brandProfile?.content_guidelines ?? ''"
                    maxlength="3000"
                    rows="4"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="Preferred terminology, style notes, and content principles"
                ></textarea>
                <InputError :message="errors.content_guidelines" />
            </div>

            <div class="grid gap-2">
                <Label for="brand-avoid-terms">Terms or claims to avoid</Label>
                <textarea
                    id="brand-avoid-terms"
                    name="avoid_terms"
                    :value="brandProfile?.avoid_terms ?? ''"
                    maxlength="2000"
                    rows="3"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="Words, phrases, or claims AI-generated copy should avoid"
                ></textarea>
                <InputError :message="errors.avoid_terms" />
            </div>

            <div>
                <Button :disabled="processing">
                    {{ processing ? 'Saving…' : 'Save brand profile' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
