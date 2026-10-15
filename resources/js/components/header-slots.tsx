import { createContext, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';

type SlotName = 'start' | 'center';

type HeaderSlotsValue = {
    targets: Partial<Record<SlotName, HTMLElement | null>>;
    register: (name: SlotName) => (element: HTMLElement | null) => void;
};

const HeaderSlotsContext = createContext<HeaderSlotsValue | null>(null);

/**
 * Lets a page render controls (search, filters) inside the app header bar.
 * The header registers its slot elements; pages portal into them.
 */
export function HeaderSlotsProvider({ children }: { children: ReactNode }) {
    const [targets, setTargets] = useState<HeaderSlotsValue['targets']>({});

    // Stable callback refs per slot, so re-renders never detach a target.
    const registrars = useMemo(() => {
        const make =
            (name: SlotName) =>
            (element: HTMLElement | null): void =>
                setTargets((current) =>
                    current[name] === element
                        ? current
                        : { ...current, [name]: element },
                );

        return { start: make('start'), center: make('center') };
    }, []);

    const value = useMemo<HeaderSlotsValue>(
        () => ({ targets, register: (name) => registrars[name] }),
        [targets, registrars],
    );

    return (
        <HeaderSlotsContext.Provider value={value}>
            {children}
        </HeaderSlotsContext.Provider>
    );
}

/** Slot element rendered by the header. */
export function HeaderSlotTarget({
    name,
    className,
}: {
    name: SlotName;
    className?: string;
}) {
    const context = useContext(HeaderSlotsContext);

    return <div ref={context?.register(name)} className={className} />;
}

/** Renders its children inside the named header slot. */
export function HeaderSlot({
    name,
    children,
}: {
    name: SlotName;
    children: ReactNode;
}) {
    const target = useContext(HeaderSlotsContext)?.targets[name];

    return target ? createPortal(children, target) : null;
}
