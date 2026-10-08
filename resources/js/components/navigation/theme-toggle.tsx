import { Button } from '@/components/ui/button';
import { useTheme } from '@/hooks/use-theme';
import { Moon, Sun } from 'lucide-react';

export function ThemeToggle() {
    const { theme, toggleTheme } = useTheme();

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={toggleTheme}
            aria-label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'}
            className="text-muted-foreground hover:text-foreground"
        >
            {theme === 'dark' ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </Button>
    );
}

export default ThemeToggle;
