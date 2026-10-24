import { cva } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

// success/warning/destructive = status; jangan dipakai sebagai warna hias.
export const badgeVariants = cva(
    'inline-flex w-fit shrink-0 items-center justify-center gap-1 whitespace-nowrap rounded-md border px-2 py-0.5 text-xs font-medium [&>svg]:pointer-events-none [&>svg]:size-3',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-primary text-primary-foreground',
                secondary: 'border-transparent bg-secondary text-secondary-foreground',
                outline: 'border-border text-foreground',
                success: 'border-success/30 bg-success/10 text-success',
                warning: 'border-warning/30 bg-warning/10 text-warning',
                destructive: 'border-destructive/30 bg-destructive/10 text-destructive',
                muted: 'border-border bg-muted text-muted-foreground',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);
