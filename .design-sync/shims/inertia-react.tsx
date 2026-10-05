// Design-sync shim for @inertiajs/react. Claude Design renders components with no
// Laravel/Inertia runtime, so Link must be a plain <a> and usePage must not throw.
// Aliased in via .design-sync/tsconfig.json paths - only the DS bundle sees this.
import { createElement, forwardRef, type AnchorHTMLAttributes, type ReactNode } from 'react';

type Href = string | { url: string };
const toHref = (href?: Href): string | undefined =>
    href == null ? undefined : typeof href === 'string' ? href : href.url;

export type InertiaLinkProps = Omit<AnchorHTMLAttributes<HTMLElement>, 'href'> & {
    href?: Href;
    as?: string;
    method?: string;
    data?: unknown;
    preserveScroll?: boolean;
    preserveState?: boolean;
    prefetch?: boolean | string | string[];
    only?: string[];
    replace?: boolean;
    children?: ReactNode;
};

const INERTIA_ONLY = ['method', 'data', 'preserveScroll', 'preserveState', 'prefetch', 'only', 'replace', 'cacheFor', 'viewTransition', 'headers', 'queryStringArrayFormat', 'async', 'except', 'onBefore', 'onStart', 'onProgress', 'onFinish', 'onCancel', 'onSuccess', 'onError', 'onCancelToken'];

export const Link = forwardRef<HTMLElement, InertiaLinkProps>(function Link({ href, as = 'a', ...rest }, ref) {
    const props: Record<string, unknown> = { ...rest, ref };
    for (const k of INERTIA_ONLY) delete props[k];
    if (as === 'a') props.href = toHref(href);
    else if (as === 'button') props.type = props.type ?? 'button';
    return createElement(as, props);
});

const fakePage = () => ({
    component: 'Design',
    url: typeof window !== 'undefined' ? window.location.pathname : '/',
    props: { errors: {}, auth: { user: null }, flash: {} } as Record<string, unknown>,
    version: null,
    clearHistory: false,
    encryptHistory: false,
});

export function usePage() {
    return fakePage();
}

const noop = () => {};
export const router = {
    on: () => noop,
    visit: noop,
    get: noop,
    post: noop,
    put: noop,
    patch: noop,
    delete: noop,
    reload: noop,
    prefetch: noop,
    cancelAll: noop,
};

export function Head(_props: { title?: string; children?: ReactNode }) {
    return null;
}
