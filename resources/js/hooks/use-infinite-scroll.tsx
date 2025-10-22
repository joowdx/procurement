import { useEffect, useRef } from 'react';

interface InfiniteScrollOptions {
    nextPageUrl: string | null;
    onLoadMore: () => void;
    threshold?: number;
}

export function useInfiniteScroll({
    nextPageUrl,
    onLoadMore,
    threshold = 300,
}: InfiniteScrollOptions) {
    const observerRef = useRef<IntersectionObserver | null>(null);
    const loadMoreRef = useRef<HTMLDivElement | null>(null);
    const isLoadingRef = useRef<boolean>(false);

    useEffect(() => {
        if (!nextPageUrl || isLoadingRef.current) {
            return;
        }

        const handleIntersect = (entries: IntersectionObserverEntry[]) => {
            const [entry] = entries;
            if (entry.isIntersecting && nextPageUrl && !isLoadingRef.current) {
                isLoadingRef.current = true;
                onLoadMore();
            }
        };

        observerRef.current = new IntersectionObserver(handleIntersect, {
            rootMargin: `${threshold}px`,
        });

        if (loadMoreRef.current) {
            observerRef.current.observe(loadMoreRef.current);
        }

        return () => {
            if (observerRef.current) {
                observerRef.current.disconnect();
            }
        };
    }, [nextPageUrl, onLoadMore, threshold]);

    useEffect(() => {
        isLoadingRef.current = false;
    }, [nextPageUrl]);

    return loadMoreRef;
}

interface LoadMoreTriggerProps {
    nextPageUrl: string | null;
    onLoadMore: () => void;
    threshold?: number;
}

export function LoadMoreTrigger({
    nextPageUrl,
    onLoadMore,
    threshold = 300,
}: LoadMoreTriggerProps) {
    const loadMoreRef = useInfiniteScroll({
        nextPageUrl,
        onLoadMore,
        threshold,
    });

    if (!nextPageUrl) {
        return null;
    }

    return <div ref={loadMoreRef} className="h-1" />;
}

