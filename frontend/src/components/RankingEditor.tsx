'use client';

import { useEffect, useRef, useState } from 'react';

export interface RankableItem {
  id: number;
  label: string;
  sublabel?: string;
  colorHex?: string | null;
}

interface Props {
  items: RankableItem[];
  initialOrder: number[]; // ids in ranked order
  onChange: (orderedIds: number[]) => void;
  disabled?: boolean;
}

/** Distance (px) from the viewport edge at which dragging starts auto-scrolling the page. */
const AUTO_SCROLL_EDGE = 60;
/** On touch, how long a finger must rest on a row to pick it up… */
const LONG_PRESS_MS = 250;
/** …without moving further than this (px) — otherwise it's a scroll. */
const LONG_PRESS_TOLERANCE = 8;

/**
 * A full ranking editor: every item must be placed in a unique position. Whole rows can be
 * dragged (Pointer Events, no dependency): straight away with a mouse, after a short press
 * on touch so a quick swipe over the list still scrolls the page. The up/down/top/bottom
 * buttons stay available for keyboard users and fine adjustments.
 */
export function RankingEditor({ items, initialOrder, onChange, disabled }: Props) {
  // RankingEditor is only ever mounted by its parent once that parent's own data has
  // finished loading (see the `if (loading) return …` guards in the pages that use it),
  // so a single lazy initializer is enough — no need to re-sync state via an effect when
  // `items`/`initialOrder` change after the fact.
  const [order, setOrder] = useState<number[]>(() => initialOrder);

  useEffect(() => {
    onChange(order);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [order]);

  const [dragId, setDragId] = useState<number | null>(null);
  const rowRefs = useRef(new Map<number, HTMLLIElement>());
  const orderRef = useRef(order);
  const pointerY = useRef(0);
  const press = useRef<{ timer: number; startX: number; startY: number } | null>(null);

  useEffect(() => {
    orderRef.current = order;
  }, [order]);

  useEffect(() => {
    if (dragId === null) return;
    const draggedId = dragId;

    // The dragged row's new index is the number of other rows whose midpoint sits above
    // the pointer. Rows are re-read from the DOM each time, so this stays correct while the
    // page scrolls underneath.
    function reorder() {
      const current = orderRef.current;
      const others = current.filter((id) => id !== draggedId);
      let index = 0;
      for (const id of others) {
        const rect = rowRefs.current.get(id)?.getBoundingClientRect();
        if (rect && pointerY.current > rect.top + rect.height / 2) index++;
      }
      if (current[index] === draggedId) return;
      setOrder([...others.slice(0, index), draggedId, ...others.slice(index)]);
    }

    let frame = 0;
    function autoScroll() {
      const y = pointerY.current;
      const bottomEdge = window.innerHeight - AUTO_SCROLL_EDGE;
      const dy = y < AUTO_SCROLL_EDGE ? -(AUTO_SCROLL_EDGE - y) / 4 : y > bottomEdge ? (y - bottomEdge) / 4 : 0;
      if (dy !== 0) {
        window.scrollBy(0, Math.round(dy));
        reorder();
      }
      frame = requestAnimationFrame(autoScroll);
    }
    frame = requestAnimationFrame(autoScroll);

    function onMove(e: PointerEvent) {
      pointerY.current = e.clientY;
      reorder();
    }
    function onEnd() {
      setDragId(null);
    }
    // Once a row is picked up by touch, the finger drags it instead of scrolling the page.
    function preventScroll(e: TouchEvent) {
      e.preventDefault();
    }

    window.addEventListener('touchmove', preventScroll, { passive: false });
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onEnd);
    window.addEventListener('pointercancel', onEnd);
    return () => {
      cancelAnimationFrame(frame);
      window.removeEventListener('touchmove', preventScroll);
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onEnd);
      window.removeEventListener('pointercancel', onEnd);
    };
  }, [dragId]);

  function cancelPress() {
    if (press.current) {
      window.clearTimeout(press.current.timer);
      press.current = null;
    }
  }

  // Don't let a pending long-press fire on an unmounted editor.
  useEffect(
    () => () => {
      if (press.current) window.clearTimeout(press.current.timer);
    },
    [],
  );

  function startDrag(e: React.PointerEvent, id: number) {
    if (disabled || e.button !== 0) return;
    if ((e.target as HTMLElement).closest('button')) return;
    pointerY.current = e.clientY;

    if (e.pointerType === 'mouse') {
      e.preventDefault();
      setDragId(id);
      return;
    }

    cancelPress();
    press.current = {
      startX: e.clientX,
      startY: e.clientY,
      timer: window.setTimeout(() => {
        press.current = null;
        navigator.vibrate?.(10);
        setDragId(id);
      }, LONG_PRESS_MS),
    };
  }

  function trackPress(e: React.PointerEvent) {
    if (!press.current) return;
    pointerY.current = e.clientY;
    if (Math.hypot(e.clientX - press.current.startX, e.clientY - press.current.startY) > LONG_PRESS_TOLERANCE) {
      cancelPress();
    }
  }

  const byId = new Map(items.map((i) => [i.id, i]));

  function move(index: number, delta: number) {
    setOrder((current) => {
      const next = [...current];
      const target = index + delta;
      if (target < 0 || target >= next.length) return current;
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
  }

  function moveToEnd(index: number, toTop: boolean) {
    setOrder((current) => {
      const next = [...current];
      const [item] = next.splice(index, 1);
      if (toTop) next.unshift(item);
      else next.push(item);
      return next;
    });
  }

  return (
    <ol className="flex flex-col gap-1 select-none">
      {order.map((id, index) => {
        const item = byId.get(id);
        if (!item) return null;
        return (
          <li
            key={id}
            ref={(el) => {
              if (el) rowRefs.current.set(id, el);
              else rowRefs.current.delete(id);
            }}
            onPointerDown={(e) => startDrag(e, id)}
            onPointerMove={trackPress}
            onPointerUp={cancelPress}
            onPointerCancel={cancelPress}
            onContextMenu={(e) => {
              if (!disabled) e.preventDefault();
            }}
            className={`flex items-center gap-2 sm:gap-3 rounded-md border px-2 sm:px-3 py-2 transition-[colors,transform] [-webkit-touch-callout:none] ${
              dragId === id
                ? 'relative z-10 scale-[1.02] border-red-500 bg-neutral-800 shadow-lg shadow-black/50 cursor-grabbing'
                : `border-neutral-800 bg-neutral-900 ${disabled ? '' : 'cursor-grab'}`
            }`}
          >
            {!disabled && (
              <span aria-hidden className="text-neutral-500" title="Glisser pour déplacer">
                ⠿
              </span>
            )}
            <span className="w-6 sm:w-7 shrink-0 text-right font-mono text-neutral-400">{index + 1}</span>
            {item.colorHex && (
              <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: item.colorHex }} />
            )}
            <span className="flex-1 min-w-0 truncate">
              {item.label}
              {item.sublabel && <span className="text-neutral-500"> · {item.sublabel}</span>}
            </span>
            <div className="flex items-center gap-1 shrink-0">
              <button
                type="button"
                disabled={disabled || index === 0}
                onClick={() => moveToEnd(index, true)}
                className="hidden sm:inline-block px-1.5 py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
                title="Monter en tête"
              >
                ⤒
              </button>
              <button
                type="button"
                disabled={disabled || index === 0}
                onClick={() => move(index, -1)}
                className="px-2.5 py-1.5 sm:px-1.5 sm:py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
                title="Monter"
              >
                ↑
              </button>
              <button
                type="button"
                disabled={disabled || index === order.length - 1}
                onClick={() => move(index, 1)}
                className="px-2.5 py-1.5 sm:px-1.5 sm:py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
                title="Descendre"
              >
                ↓
              </button>
              <button
                type="button"
                disabled={disabled || index === order.length - 1}
                onClick={() => moveToEnd(index, false)}
                className="hidden sm:inline-block px-1.5 py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
                title="Descendre en bas"
              >
                ⤓
              </button>
            </div>
          </li>
        );
      })}
    </ol>
  );
}
