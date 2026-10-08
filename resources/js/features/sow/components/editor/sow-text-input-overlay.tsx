import { MAX_TEXT_LENGTH } from '../../utils/annotation-defaults';
import * as React from 'react';

export interface SowTextInputOverlayProps {
    rect: { left: number; top: number; width: number; height: number };
    fontSize: number;
    textColor: string;
    borderRadius: number;
    initialValue: string;
    onCommit: (text: string) => void;
    onCancel: () => void;
}

/**
 * §6.3 Yellow #5 — Konva has no native text input, so a DOM textarea is
 * absolutely positioned over the shape with mirrored font metrics. Commits
 * on blur or Enter (Shift+Enter inserts a newline), cancels on Escape.
 */
export function SowTextInputOverlay({
    rect,
    fontSize,
    textColor: _textColor,
    borderRadius,
    initialValue,
    onCommit,
    onCancel,
}: Readonly<SowTextInputOverlayProps>) {
    const [value, setValue] = React.useState(initialValue);
    const ref = React.useRef<HTMLTextAreaElement>(null);

    React.useEffect(() => {
        ref.current?.focus();
        ref.current?.select();
    }, []);

    const commit = () => onCommit(value);

    return (
        <div
            style={{
                position: 'absolute',
                left: rect.left,
                top: rect.top,
                zIndex: 20,
            }}
            className="flex flex-col items-start gap-1"
        >
            <textarea
                ref={ref}
                value={value}
                placeholder="Enter callout note (max 200 chars)…"
                maxLength={MAX_TEXT_LENGTH}
                onChange={(e) => setValue(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        commit();
                    } else if (e.key === 'Escape') {
                        onCancel();
                    }
                }}
                style={{
                    width: Math.max(160, rect.width),
                    height: Math.max(36, rect.height),
                    fontSize: Math.max(16, fontSize),
                    lineHeight: 1.28,
                    fontWeight: 700,
                    fontFamily: 'Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    color: '#000000',
                    textAlign: 'center',
                    background: '#FFFFFF',
                    border: '2px solid #2563EB',
                    borderRadius,
                    padding: '6px 8px',
                    resize: 'none',
                    overflow: 'hidden',
                    outline: 'none',
                    boxShadow: '0 0 0 2px rgba(37, 99, 235, 0.25), 0 8px 24px rgba(0, 0, 0, 0.2)',
                }}
            />
            <div className="flex items-center gap-2 rounded bg-slate-900/95 px-1.5 py-0.5 text-[10px] font-medium shadow-xs backdrop-blur-xs">
                <span className="text-slate-300">
                    Enter to save · Shift+Enter newline · Esc cancel
                </span>
                <span
                    className={
                        value.length >= MAX_TEXT_LENGTH * 0.9
                            ? 'tabular-nums text-amber-400'
                            : 'tabular-nums text-slate-400'
                    }
                >
                    {value.length}/{MAX_TEXT_LENGTH}
                </span>
            </div>
        </div>
    );
}
