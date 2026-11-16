(function () {
    'use strict';

    const searchInput = document.getElementById('searchInput');
    const searchForm = searchInput ? searchInput.closest('form') : null;
    const suggestions = document.getElementById('searchSuggestions');
    const mobileViewport = window.matchMedia('(max-width: 800px)');

    if (!searchInput || !searchForm || !suggestions) {
        return;
    }

    let debounceTimer = null;
    let requestSequence = 0;

    function getProductLimit() {
        return mobileViewport.matches ? 3 : 5;
    }

    function setExpanded(isExpanded) {
        suggestions.hidden = !isExpanded;
        searchInput.setAttribute('aria-expanded', String(isExpanded));
    }

    function clearSuggestions() {
        requestSequence++;
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        suggestions.replaceChildren();
        setExpanded(false);
    }

    function appendHeading(group, title) {
        const heading = document.createElement('h2');
        heading.className = 'search-suggestion-heading';
        heading.textContent = title;
        group.appendChild(heading);
    }

    function renderProducts(products, group) {
        products.forEach(function (product) {
            const link = document.createElement('a');
            link.className = 'search-suggestion search-suggestion-product';
            link.href =
                '/Meetory/meetory/goods/goods-detail.php?goods_id=' +
                encodeURIComponent(product.goods_id);

            if (product.image_path) {
                const image = document.createElement('img');
                image.className = 'search-suggestion-image';
                image.src = '/Meetory/' + product.image_path;
                image.alt = '';
                image.loading = 'lazy';
                link.appendChild(image);
            } else {
                const placeholder = document.createElement('span');
                placeholder.className = 'search-suggestion-placeholder';
                const icon = document.createElement('i');
                icon.className = 'fa-regular fa-image';
                icon.setAttribute('aria-hidden', 'true');
                placeholder.appendChild(icon);
                link.appendChild(placeholder);
            }

            const details = document.createElement('span');
            details.className = 'search-suggestion-details';

            const name = document.createElement('span');
            name.className = 'search-suggestion-name';
            name.textContent = product.goods_name;

            const price = document.createElement('span');
            price.className = 'search-suggestion-price';
            price.textContent =
                Number(product.price).toLocaleString('ja-JP') + '円';

            details.appendChild(name);
            details.appendChild(price);
            link.appendChild(details);
            group.appendChild(link);
        });
    }

    function renderCategories(categories, group) {
        categories.forEach(function (category) {
            const link = document.createElement('a');
            link.className = 'search-suggestion search-suggestion-category';
            link.href =
                '/Meetory/meetory/goods/goods-list.php?category_id=' +
                encodeURIComponent(category.category_id);

            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-tag';
            icon.setAttribute('aria-hidden', 'true');

            const name = document.createElement('span');
            name.className = 'search-suggestion-name';
            name.textContent = category.category_name;

            link.appendChild(icon);
            link.appendChild(name);
            group.appendChild(link);
        });
    }

    function renderResults(results) {
        suggestions.replaceChildren();
        const products = results.products.slice(0, getProductLimit());
        const categories = results.categories.slice(0, 3);

        if (products.length > 0) {
            const group = document.createElement('section');
            group.className = 'search-suggestion-group';
            appendHeading(group, '商品');
            renderProducts(products, group);
            suggestions.appendChild(group);
        }

        if (categories.length > 0) {
            const group = document.createElement('section');
            group.className = 'search-suggestion-group';
            appendHeading(group, 'カテゴリー');
            renderCategories(categories, group);
            suggestions.appendChild(group);
        }

        if (products.length === 0 && categories.length === 0) {
            const message = document.createElement('p');
            message.className = 'search-suggestion-message';
            message.textContent = '候補が見つかりませんでした。';
            suggestions.appendChild(message);
        }

        setExpanded(true);
    }

    function renderError() {
        suggestions.replaceChildren();
        const message = document.createElement('p');
        message.className = 'search-suggestion-message';
        message.textContent = '検索候補を読み込めませんでした。';
        suggestions.appendChild(message);
        setExpanded(true);
    }

    async function loadSuggestions(query, sequence) {
        try {
            const url = new URL(
                '/Meetory/meetory/goods/search-suggestions.php',
                window.location.origin
            );
            url.searchParams.set('q', query);
            url.searchParams.set('limit', String(getProductLimit()));

            const response = await fetch(url, {
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) {
                throw new Error('検索候補の取得に失敗しました。');
            }

            const results = await response.json();
            if (
                sequence !== requestSequence ||
                query !== searchInput.value.trim()
            ) {
                return;
            }
            renderResults(results);
        } catch (error) {
            if (sequence === requestSequence) {
                console.error(error);
                renderError();
            }
        }
    }

    searchInput.addEventListener('input', function () {
        const query = searchInput.value.trim();
        const sequence = ++requestSequence;

        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }

        if (query === '') {
            suggestions.replaceChildren();
            setExpanded(false);
            return;
        }

        debounceTimer = setTimeout(function () {
            loadSuggestions(query, sequence);
        }, 180);
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            clearSuggestions();
        }
    });

    searchForm.addEventListener('submit', clearSuggestions);

    document.addEventListener('pointerdown', function (event) {
        if (!searchForm.contains(event.target)) {
            clearSuggestions();
        }
    });

    function refreshForViewportChange() {
        if (suggestions.hidden || searchInput.value.trim() === '') {
            return;
        }
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        const sequence = ++requestSequence;
        loadSuggestions(searchInput.value.trim(), sequence);
    }

    if (typeof mobileViewport.addEventListener === 'function') {
        mobileViewport.addEventListener('change', refreshForViewportChange);
    } else if (typeof mobileViewport.addListener === 'function') {
        mobileViewport.addListener(refreshForViewportChange);
    }
})();
