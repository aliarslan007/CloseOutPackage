import { create } from 'zustand';

interface SidebarState {
    collapsed: boolean;
    mobileOpen: boolean;
    toggleCollapsed: () => void;
    setCollapsed: (collapsed: boolean) => void;
    toggleMobileOpen: () => void;
    setMobileOpen: (open: boolean) => void;
}

export const useSidebarStore = create<SidebarState>((set) => ({
    collapsed: false,
    mobileOpen: false,
    toggleCollapsed: () => set((state) => ({ collapsed: !state.collapsed })),
    setCollapsed: (collapsed) => set({ collapsed }),
    toggleMobileOpen: () => set((state) => ({ mobileOpen: !state.mobileOpen })),
    setMobileOpen: (mobileOpen) => set({ mobileOpen }),
}));

export default useSidebarStore;
