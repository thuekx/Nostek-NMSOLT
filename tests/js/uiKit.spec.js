import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { h, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { cn } from '@/lib/utils';
import { deviceWriteDirective } from '@/lib/deviceWrites';

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en: { common: { close: 'Close' } } } });

describe('cn', () => {
    it('lets the later Tailwind class win', () => {
        expect(cn('px-2 text-sm', 'px-4')).toBe('text-sm px-4');
        expect(cn('bg-primary', false && 'hidden', ['rounded-md'])).toBe('bg-primary rounded-md');
    });
});

describe('Button', () => {
    it('renders semantic variant classes and merges the caller class', () => {
        const w = mount(Button, { props: { variant: 'destructive', size: 'sm', class: 'px-8' }, slots: { default: 'Reboot' } });
        const el = w.get('button');
        expect(el.attributes('data-slot')).toBe('button');
        expect(el.classes()).toContain('bg-destructive');
        expect(el.classes()).toContain('px-8');
        expect(el.classes()).not.toContain('px-3');
        expect(el.text()).toBe('Reboot');
    });

    it('keeps one root element so v-device-write locks it (WRITE-UI-1)', () => {
        const directive = deviceWriteDirective(() => 'Locked: writes off');
        const w = mount({
            components: { Button },
            directives: { 'device-write': directive },
            template: '<Button v-device-write="{ enabled: false, gated: [] }">Reboot</Button>',
        });
        const el = w.get('button');
        expect(el.attributes('disabled')).toBeDefined();
        expect(el.attributes('aria-disabled')).toBe('true');
        expect(el.attributes('title')).toBe('Locked: writes off');
    });
});

describe('Badge', () => {
    it.each(['success', 'warning', 'destructive'])('maps status %s to its semantic token', (variant) => {
        const w = mount(Badge, { props: { variant }, slots: { default: 'x' } });
        expect(w.get('span').classes()).toContain(`text-${variant}`);
    });
});

describe('Card', () => {
    it('composes header, title and content on semantic surfaces', () => {
        const w = mount({
            components: { Card, CardHeader, CardTitle, CardContent },
            template: '<Card><CardHeader><CardTitle>OLT</CardTitle></CardHeader><CardContent>4</CardContent></Card>',
        });
        const card = w.get('[data-slot=card]');
        expect(card.classes()).toContain('bg-card');
        expect(card.classes()).toContain('border-border');
        expect(w.get('[data-slot=card-title]').element.tagName).toBe('H3');
    });
});

describe('Input', () => {
    it('supports v-model', async () => {
        const w = mount(Input, { props: { modelValue: 'ZTEG', 'onUpdate:modelValue': (v) => w.setProps({ modelValue: v }) } });
        await w.get('input').setValue('ZTEG8C1A');
        expect(w.props('modelValue')).toBe('ZTEG8C1A');
    });
});

describe('Tabs', () => {
    it('shows the selected panel only', async () => {
        const w = mount({
            components: { Tabs, TabsList, TabsTrigger, TabsContent },
            template: `<Tabs default-value="rx">
                <TabsList><TabsTrigger value="rx">RX</TabsTrigger><TabsTrigger value="traffic">Traffic</TabsTrigger></TabsList>
                <TabsContent value="rx">rx-panel</TabsContent><TabsContent value="traffic">traffic-panel</TabsContent>
            </Tabs>`,
        }, { attachTo: document.body });
        expect(w.text()).toContain('rx-panel');
        expect(w.text()).not.toContain('traffic-panel');
        w.unmount();
    });
});

describe('Dialog', () => {
    it('opens from its trigger and has a translated close button', async () => {
        const w = mount({
            render: () => h(Dialog, null, {
                default: () => [
                    h(DialogTrigger, null, { default: () => 'Open' }),
                    h(DialogContent, null, { default: () => [h(DialogTitle, null, { default: () => 'Impact preview' })] }),
                ],
            }),
        }, { attachTo: document.body, global: { plugins: [i18n] } });
        await w.get('button').trigger('click');
        await nextTick();
        expect(document.body.textContent).toContain('Impact preview');
        expect(document.body.querySelector('[data-slot=dialog-content] .sr-only')?.textContent).toBe('Close');
        w.unmount();
    });
});
