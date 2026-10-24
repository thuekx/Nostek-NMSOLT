import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Gabung kelas Tailwind tanpa bentrok (`px-2` lalu `px-4` → `px-4`).
 * Dipakai komponen resources/js/Components/ui (gaya shadcn-vue).
 */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

/** Salin props tanpa `class`, supaya kelas tidak diteruskan dua kali ke primitif Reka. */
export function omitClass(props) {
    const { class: _class, ...rest } = props;
    return rest;
}
