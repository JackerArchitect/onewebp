document.addEventListener("DOMContentLoaded", function() {
    const MAX_CONCURRENT = 3;
    let loadingCount = 0;
    const queue = [];

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                queue.push(entry.target);
                observer.unobserve(entry.target);
                processQueue();
            }
        });
    }, { rootMargin: '100px' });

    document.querySelectorAll('.onewebp-lazy-img').forEach(img => observer.observe(img));

    function processQueue() {
        if (loadingCount >= MAX_CONCURRENT || queue.length === 0) return;
        loadingCount++;
        const img = queue.shift();
        const realSrc = img.getAttribute('data-src');
        const picture = img.closest('picture');

        if (picture) {
            const source = picture.querySelector('source[data-srcset]');
            if (source) { source.srcset = source.getAttribute('data-srcset'); source.removeAttribute('data-srcset'); }
        }

        img.src = realSrc;
        img.removeAttribute('data-src');
        img.onload = img.onerror = () => {
            img.style.opacity = '1';
            loadingCount--;
            processQueue();
        };
    }
});
