(function() {
    'use strict';

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyLoad);
    } else {
        initLazyLoad();
    }

    function initLazyLoad() {
        const MAX_CONCURRENT = 3;
        let loadingCount = 0;
        const queue = [];
        let observer = null;

        // Check if IntersectionObserver is supported
        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        queue.push(entry.target);
                        observer.unobserve(entry.target);
                        processQueue();
                    }
                });
            }, { 
                rootMargin: '100px',
                threshold: 0.01
            });

            document.querySelectorAll('.onewebp-lazy-img').forEach(function(img) {
                observer.observe(img);
            });
        } else {
            // Fallback: load all images immediately
            document.querySelectorAll('.onewebp-lazy-img').forEach(function(img) {
                loadImage(img);
            });
        }

        function processQueue() {
            if (loadingCount >= MAX_CONCURRENT || queue.length === 0) return;
            
            loadingCount++;
            const img = queue.shift();
            loadImage(img);
        }

        function loadImage(img) {
            if (!img) {
                loadingCount--;
                processQueue();
                return;
            }

            const realSrc = img.getAttribute('data-src');
            const picture = img.closest('picture');

            if (picture) {
                const source = picture.querySelector('source[data-srcset]');
                if (source) {
                    source.srcset = source.getAttribute('data-srcset');
                    source.removeAttribute('data-srcset');
                }
            }

            // Handle srcset for lazy load
            const dataSrcset = img.getAttribute('data-srcset');
            if (dataSrcset) {
                img.srcset = dataSrcset;
                img.removeAttribute('data-srcset');
            }

            if (realSrc) {
                img.src = realSrc;
                img.removeAttribute('data-src');
            }

            img.onload = function() {
                img.style.opacity = '1';
                img.classList.remove('onewebp-lazy-img');
                img.onload = null;
                img.onerror = null;
                loadingCount--;
                processQueue();
            };

            img.onerror = function() {
                // If WebP fails, try original src
                img.style.opacity = '1';
                img.classList.remove('onewebp-lazy-img');
                img.onload = null;
                img.onerror = null;
                loadingCount--;
                processQueue();
            };
        }
    }
})();
