<?php

declare(strict_types=1);

use App\Core\App;

/**
 * Tailwind Play CDN bootstrap.
 *
 * The compiled stylesheet (public/assets/css/app.css) stays linked: it carries
 * the component layer (.glass, .blob, .reveal, .a-table) and the @keyframes,
 * which the CDN cannot generate. This partial only adds the runtime utility
 * compiler, so a page still renders correctly with the CDN blocked and looks
 * identical when it loads.
 *
 * The config below mirrors the @theme block in resources/css/input.css one for
 * one. If a token changes there, change it here too.
 *
 * Order matters: the CDN script defines the global, so the config must follow it.
 *
 * @var string $nonce
 */

$cspNonce = (string) ($nonce ?? $cspNonce ?? App::cspNonce());
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script nonce="<?= e($cspNonce) ?>">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f4f4ff', 100: '#eaeaff', 200: '#d7d6fe', 300: '#bcb3fd',
                            400: '#9c86fa', 500: '#7c50f2', 600: '#6a31e0', 700: '#5823c0',
                            800: '#47209a', 900: '#2f1670', 950: '#1c0b47',
                        },
                        accent: {
                            50: '#ecfeff', 100: '#cffafe', 200: '#a5f3fc', 300: '#67e8f9',
                            400: '#22d3ee', 500: '#06b6d4', 600: '#0891b2', 700: '#0e7490',
                            800: '#155e75', 900: '#164e63', 950: '#083344',
                        },
                        lime: {
                            50: '#f7fee7', 100: '#ecfccb', 200: '#d9f99d', 300: '#bef264',
                            400: '#a3e635', 500: '#84cc16', 600: '#65a30d', 700: '#4d7c0f',
                            800: '#3f6212', 900: '#365314', 950: '#1a2e05',
                        },
                        amber: {
                            50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d',
                            400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309',
                            800: '#92400e', 900: '#78350f', 950: '#451a03',
                        },
                        rose: {
                            50: '#fff1f2', 100: '#ffe4e6', 200: '#fecdd3', 300: '#fda4af',
                            400: '#fb7185', 500: '#f43f5e', 600: '#e11d48', 700: '#be123c',
                            800: '#9f1239', 900: '#881337', 950: '#4c0519',
                        },
                        ink: { DEFAULT: '#0b1020', 400: '#6b7399', 700: '#283055' },
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI',
                            'Roboto', 'Noto Sans Bengali', 'Hind Siliguri', 'Nirmala UI', 'Arial', 'sans-serif'],
                        display: ['Inter', 'ui-sans-serif', 'system-ui', 'Noto Sans Bengali', 'sans-serif'],
                    },
                    borderRadius: {
                        brand: '1.25rem',
                        card: '1.75rem',
                        pill: '999px',
                    },
                    boxShadow: {
                        soft: '0 10px 30px -12px rgb(11 16 32 / 0.28)',
                        card: '0 24px 60px -24px rgb(11 16 32 / 0.35)',
                        pop: '0 18px 45px -18px rgb(124 80 242 / 0.55)',
                        glow: '0 0 0 1px rgb(124 80 242 / 0.22), 0 12px 40px -12px rgb(108 49 224 / 0.55)',
                    },
                    transitionTimingFunction: {
                        bounce: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
                        soft: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    },
                    keyframes: {
                        'zs-float': {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-14px)' },
                        },
                        'zs-blob': {
                            '0%, 100%': { 'border-radius': '62% 38% 55% 45% / 55% 45% 60% 40%', transform: 'translate3d(0, 0, 0) scale(1)' },
                            '33%': { 'border-radius': '44% 56% 39% 61% / 60% 39% 61% 40%', transform: 'translate3d(22px, -18px, 0) scale(1.06)' },
                            '66%': { 'border-radius': '55% 45% 62% 38% / 40% 62% 38% 60%', transform: 'translate3d(-18px, 14px, 0) scale(0.96)' },
                        },
                        'zs-marquee': {
                            from: { transform: 'translateX(0)' },
                            to: { transform: 'translateX(-50%)' },
                        },
                        'zs-shimmer': {
                            from: { 'background-position': '-160% 0' },
                            to: { 'background-position': '260% 0' },
                        },
                        'zs-pop': {
                            '0%': { opacity: '0', transform: 'scale(0.82)' },
                            '70%': { opacity: '1', transform: 'scale(1.04)' },
                            '100%': { opacity: '1', transform: 'scale(1)' },
                        },
                        'zs-pulse-ring': {
                            '0%': { 'box-shadow': '0 0 0 0 rgb(124 80 242 / 0.45)' },
                            '70%': { 'box-shadow': '0 0 0 18px rgb(124 80 242 / 0)' },
                            '100%': { 'box-shadow': '0 0 0 0 rgb(124 80 242 / 0)' },
                        },
                        'zs-gradient': {
                            '0%': { 'background-position': '0% 50%' },
                            '50%': { 'background-position': '100% 50%' },
                            '100%': { 'background-position': '0% 50%' },
                        },
                    },
                    animation: {
                        float: 'zs-float 6s cubic-bezier(0.4, 0, 0.2, 1) infinite',
                        blob: 'zs-blob 14s cubic-bezier(0.4, 0, 0.2, 1) infinite',
                        marquee: 'zs-marquee 30s linear infinite',
                        shimmer: 'zs-shimmer 1.8s linear infinite',
                        pop: 'zs-pop 0.34s cubic-bezier(0.34, 1.56, 0.64, 1) both',
                        'pulse-ring': 'zs-pulse-ring 2.4s cubic-bezier(0.4, 0, 0.2, 1) infinite',
                        gradient: 'zs-gradient 7s ease infinite',
                    },
                },
            },
        };
    </script>
