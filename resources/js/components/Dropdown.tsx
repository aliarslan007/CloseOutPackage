import { Transition } from '@headlessui/react';
import { InertiaLinkProps, Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    createContext,
    Dispatch,
    PropsWithChildren,
    SetStateAction,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';

interface DropDownContextType {
    open: boolean;
    setOpen: Dispatch<SetStateAction<boolean>>;
    toggleOpen: () => void;
}

const DropDownContext = createContext<DropDownContextType>({
    open: false,
    setOpen: () => {},
    toggleOpen: () => {},
});

const Dropdown = ({ children }: PropsWithChildren) => {
    const [open, setOpen] = useState(false);

    const toggleOpen = useCallback(() => {
        setOpen((previousState) => !previousState);
    }, [setOpen]);

    const contextValue = useMemo(
        () => ({ open, setOpen, toggleOpen }),
        [open, setOpen, toggleOpen]
    );

    return (
        <DropDownContext.Provider value={contextValue}>
            <div className="relative">{children}</div>
        </DropDownContext.Provider>
    );
};

const Trigger = ({ children }: PropsWithChildren) => {
    const { open, setOpen, toggleOpen } = useContext(DropDownContext);

    return (
        <>
            <button
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                className="rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-zinc-500"
                onClick={toggleOpen}
            >
                {children}
            </button>

            {open && (
                <button
                    type="button"
                    aria-label="Close menu"
                    tabIndex={-1}
                    className="fixed inset-0 z-40 cursor-default"
                    onClick={() => setOpen(false)}
                />
            )}
        </>
    );
};

interface DropdownContentProps {
    align?: 'left' | 'right' | 'center';
    width?: string;
    contentClasses?: string;
    className?: string;
}

const Content = ({
    align = 'right',
    width = '48',
    contentClasses = 'py-1 bg-white dark:bg-zinc-900',
    className = '',
    children,
}: PropsWithChildren<DropdownContentProps>) => {
    const { open, setOpen } = useContext(DropDownContext);

    let alignmentClasses = 'origin-top';

    if (align === 'left') {
        alignmentClasses = 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (align === 'right') {
        alignmentClasses = 'ltr:origin-top-right rtl:origin-top-left end-0';
    } else if (align === 'center') {
        alignmentClasses = 'origin-top left-1/2 -translate-x-1/2';
    }

    let widthClasses = '';

    if (width === '48') {
        widthClasses = 'w-48';
    } else if (width === '80') {
        widthClasses = 'w-80';
    }

    return (
        <Transition
            show={open}
            enter="transition ease-out duration-200"
            enterFrom="opacity-0 scale-95"
            enterTo="opacity-100 scale-100"
            leave="transition ease-in duration-75"
            leaveFrom="opacity-100 scale-100"
            leaveTo="opacity-0 scale-95"
        >
            <div
                role="menu"
                tabIndex={-1}
                className={cn(
                    'absolute z-50 mt-2 rounded-md shadow-lg',
                    alignmentClasses,
                    widthClasses,
                    className
                )}
                onClick={() => setOpen(false)}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        setOpen(false);
                    }
                }}
            >
                <div className={cn('rounded-md ring-1 ring-black/5 dark:ring-white/10', contentClasses)}>
                    {children}
                </div>
            </div>
        </Transition>
    );
};

const DropdownLink = ({ className = '', children, ...props }: InertiaLinkProps) => {
    return (
        <Link
            {...props}
            role="menuitem"
            className={
                'block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-zinc-200 dark:hover:bg-zinc-800 dark:focus:bg-zinc-800 ' +
                className
            }
        >
            {children}
        </Link>
    );
};

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
