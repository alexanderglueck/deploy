<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { AnsiUp } from 'ansi_up';

const props = defineProps({
    content: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    autoScroll: { type: Boolean, default: false },
    maxHeight: { type: String, default: '' },
});

const box = ref(null);

// Convert the raw SSH output (which contains ANSI colour escape codes from
// composer/npm/git/etc.) into safe, coloured HTML. AnsiUp HTML-escapes the
// input, so binding the result with v-html is XSS-safe.
const html = computed(() => {
    const text = props.content ?? '';

    if (text === '') {
        return '';
    }

    const ansi = new AnsiUp();
    ansi.use_classes = false;

    return ansi.ansi_to_html(text);
});

watch(() => props.content, async () => {
    if (! props.autoScroll) {
        return;
    }

    await nextTick();

    if (box.value) {
        box.value.scrollTop = box.value.scrollHeight;
    }
}, { immediate: true });
</script>

<template>
    <pre
        ref="box"
        class="text-gray-100 bg-gray-900 rounded p-3 overflow-auto text-sm whitespace-pre-wrap"
        :class="maxHeight"
    ><span v-if="html" v-html="html" /><span v-else class="text-gray-500">{{ placeholder }}</span></pre>
</template>
