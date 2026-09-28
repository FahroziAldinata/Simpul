import { computed, isRef, onMounted, onUnmounted, ref, type Ref } from 'vue';

export interface UseAutosaveOptions<T> {
    key: string | Ref<string>;
    formData: Ref<T> | T;
    intervalMs?: number;
    enabled?: Ref<boolean> | boolean;
}

export function useAutosave<T extends object>(
    optionsOrKey: UseAutosaveOptions<T> | string | Ref<string>,
    formStateMaybe?: Ref<T> | T,
    intervalMsMaybe: number = 10000
) {
    const isOptionsObject = typeof optionsOrKey === 'object' && !isRef(optionsOrKey) && 'formData' in optionsOrKey;
    const rawKey = isOptionsObject ? optionsOrKey.key : optionsOrKey;
    const formState = isOptionsObject ? optionsOrKey.formData : formStateMaybe!;
    const intervalMs = isOptionsObject ? (optionsOrKey.intervalMs ?? 10000) : intervalMsMaybe;
    const enabledCondition = isOptionsObject ? (optionsOrKey.enabled ?? true) : true;

    const getKey = () => (isRef(rawKey) ? rawKey.value : (rawKey as string));

    const isEnabled = computed(() => {
        if (isRef(enabledCondition)) {
            return enabledCondition.value;
        }
        return Boolean(enabledCondition);
    });

    const hasDraft = ref(false);
    const lastSavedAt = ref<Date | null>(null);
    let timerId: ReturnType<typeof setInterval> | null = null;

    function getFormValue(): T {
        if (isRef(formState)) {
            return formState.value;
        }
        return formState;
    }

    function checkExistingDraft() {
        try {
            const raw = localStorage.getItem(getKey());
            if (raw) {
                const parsed = JSON.parse(raw) as Record<string, unknown>;
                if (parsed && typeof parsed === 'object') {
                    hasDraft.value = true;
                    if (parsed._savedAt && typeof parsed._savedAt === 'string') {
                        lastSavedAt.value = new Date(parsed._savedAt);
                    }
                }
            } else {
                hasDraft.value = false;
            }
        } catch {
            hasDraft.value = false;
        }
    }

    function saveDraft() {
        if (!isEnabled.value) return;

        try {
            const data = getFormValue();
            const payload: Record<string, unknown> = {
                ...data,
                _savedAt: new Date().toISOString(),
            };
            localStorage.setItem(getKey(), JSON.stringify(payload));
            lastSavedAt.value = new Date();
            hasDraft.value = true;
        } catch {
            // Silently fail if localStorage quota exceeded
        }
    }

    function restoreDraft(): Partial<T> | null {
        try {
            const raw = localStorage.getItem(getKey());
            if (!raw) return null;
            const parsed = JSON.parse(raw) as Record<string, unknown>;
            delete parsed._savedAt;

            const target = getFormValue();
            if (target && typeof target === 'object') {
                Object.assign(target, parsed);
            }
            return parsed as Partial<T>;
        } catch {
            return null;
        }
    }

    function clearDraft() {
        try {
            localStorage.removeItem(getKey());
            hasDraft.value = false;
            lastSavedAt.value = null;
        } catch {
            // Silently fail
        }
    }

    onMounted(() => {
        checkExistingDraft();
        timerId = setInterval(() => {
            saveDraft();
        }, intervalMs);
    });

    onUnmounted(() => {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
    });

    return {
        hasDraft,
        hasSavedDraft: hasDraft,
        lastSavedAt,
        saveDraft,
        restoreDraft,
        clearDraft,
    };
}
