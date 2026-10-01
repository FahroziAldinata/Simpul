// Ziggy — global route() helper injected by app.js at runtime.
// This file has no imports so TypeScript treats it as a "script" (not a module),
// making the declare visible globally across the entire project.
declare function route(
    name: string,
    params?: string | number | Record<string, unknown> | Array<string | number>,
    absolute?: boolean,
): string;
