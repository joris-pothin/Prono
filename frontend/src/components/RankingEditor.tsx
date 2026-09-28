'use client';

import { useEffect, useState } from 'react';

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

/**
 * A full ranking editor: every item must be placed in a unique position. No drag-and-drop
 * dependency — just up/down/top/bottom controls, which stays fully usable on mobile.
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
    <ol className="flex flex-col gap-1">
      {order.map((id, index) => {
        const item = byId.get(id);
        if (!item) return null;
        return (
          <li
            key={id}
            className="flex items-center gap-3 rounded-md border border-neutral-800 bg-neutral-900 px-3 py-2"
          >
            <span className="w-7 shrink-0 text-right font-mono text-neutral-400">{index + 1}</span>
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
                className="px-1.5 py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
                title="Monter en tête"
              >
                ⤒
              </button>
              <button
                type="button"
                disabled={disabled || index === 0}
                onClick={() => move(index, -1)}
                className="px-1.5 py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
                title="Monter"
              >
                ↑
              </button>
              <button
                type="button"
                disabled={disabled || index === order.length - 1}
                onClick={() => move(index, 1)}
                className="px-1.5 py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
                title="Descendre"
              >
                ↓
              </button>
              <button
                type="button"
                disabled={disabled || index === order.length - 1}
                onClick={() => moveToEnd(index, false)}
                className="px-1.5 py-1 rounded text-xs border border-neutral-700 disabled:opacity-30 hover:bg-neutral-800"
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
