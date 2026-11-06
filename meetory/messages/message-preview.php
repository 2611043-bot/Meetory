<?php
// Meetory共通ヘッダーを読み込む
require_once '../../meetory-header.php';
?>

<style>
    * {
        box-sizing: border-box;
    }

    /* チャット全体 */
    .chat-container {
        width: 100%;
        max-width: 850px;
        margin: 0 auto;
        padding: 16px;
        color: #333;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        border: 1px solid #e1e8ed;
        background-color: #fff;
    }

    /* 通常メッセージ */
    .message {
        margin: 8px 0;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
        line-height: 1.6;
    }

    .message.left {
        text-align: left;
    }

    .message.right {
        text-align: right;
    }

    /* 商品カード */
    .product-card {
        margin: 12px 0;
        padding: 12px;
        border: 2px solid #1a73e8;
        border-radius: 4px;
        background-color: #f6f8fa;
    }

    /* 商品カードの見出し */
    .product-header {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .product-header:focus-visible {
        outline: 2px solid #1a73e8;
        outline-offset: 3px;
    }

    /* 矢印 */
    .arrow-icon {
        flex-shrink: 0;
        font-size: 12px;
        transition: transform 0.2s ease;
    }

    .product-card.open > .product-header .arrow-icon {
        transform: rotate(90deg);
    }

    /* 商品名など */
    .product-summary {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .product-subtitle {
        margin-top: 3px;
        color: #666;
        font-size: 0.85em;
    }

    /* 商品の詳細部分 */
    .accordion-content {
        display: none;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #aaa;
    }

    .product-card.open > .accordion-content {
        display: block;
    }

    /* 商品画像 */
    .product-image {
        display: none;
        width: 100%;
        max-width: 240px;
        max-height: 220px;
        margin: 10px 0;
        object-fit: contain;
        background-color: #eee;
    }

    .product-image.visible {
        display: block;
    }

    /* 商品価格 */
    .product-price {
        margin: 8px 0;
        font-weight: bold;
    }

    /* 商品説明 */
    .product-description {
        margin: 8px 0;
        font-size: 0.9em;
        line-height: 1.6;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    /* 商品専用チャットの見出し */
    .product-chat-title {
        margin: 12px 0 8px;
        color: #666;
        text-align: center;
        font-size: 0.9em;
    }

    /* 入力欄と送信ボタン */
    .input-group {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .input-group input {
        flex: 1;
        min-width: 0;
        padding: 8px 12px;
        border: 1px solid #d0d7de;
        border-radius: 20px;
        outline: none;
    }

    .input-group input:focus {
        border-color: #1a73e8;
    }

    .input-group button {
        flex-shrink: 0;
        padding: 8px 16px;
        border: 1px solid #1a73e8;
        border-radius: 20px;
        color: #1a73e8;
        background-color: #fff;
        cursor: pointer;
        font-weight: bold;
    }

    .input-group button:hover {
        background-color: #e8f0fe;
    }

    /* 通常チャットの入力欄 */
    .footer-input {
        margin-top: 20px;
        padding-top: 12px;
        border-top: 1px solid #ddd;
    }

    /* メッセージがない場合 */
    .empty-message {
        margin: 8px 0;
        color: #777;
        text-align: center;
        font-size: 0.9em;
    }

    @media (max-width: 480px) {
        .chat-container {
            padding: 12px;
        }

        .input-group {
            gap: 6px;
        }

        .input-group input {
            padding: 8px;
        }

        .input-group button {
            padding: 8px 12px;
        }
    }
</style>

<main class="chat-container">

    <!-- 通常の会話と商品カードを表示する場所 -->
    <div id="mainMessages"></div>

    <!-- 通常チャットの入力欄 -->
    <form class="input-group footer-input" id="mainForm">

        <input
            id="mainInput"
            type="text"
            maxlength="2000"
            placeholder="メッセージを入力"
            aria-label="通常メッセージを入力"
            autocomplete="off"
        >

        <button type="submit">送信</button>

    </form>

</main>

<script>
"use strict";

/*
 * ブラウザにテストデータを保存するためのキー
 */
const STORAGE_KEY = "meetory_message_demo_v1";

const mainMessages = document.getElementById("mainMessages");


/*
 * 最初に表示するサンプルデータ
 *
 * type が text    → 通常メッセージ
 * type が product → 商品カード
 *
 * 商品カードは、それぞれ productMessages を持ちます。
 */
const initialItems = [

    {
        id: "text-1",
        type: "text",
        side: "left",
        text: "あいうえお",
        time: "10:00"
    },

    {
        id: "text-2",
        type: "text",
        side: "right",
        text: "かきくけこ",
        time: "10:01"
    },

    {
        id: "product-1",
        type: "product",
        side: "left",
        name: "指定した商品の情報",
        subtitle: "画像あり",
        image: "",
        description: "",
        price: "",
        open: false,
        time: "10:02",
        productMessages: []
    },

    {
        id: "text-3",
        type: "text",
        side: "left",
        text: "さしすせそ",
        time: "10:03"
    },

    {
        id: "text-4",
        type: "text",
        side: "right",
        text: "たちつてと",
        time: "10:04"
    },

    {
        id: "product-2",
        type: "product",
        side: "right",
        name: "指定した商品の情報",
        subtitle: "画像など",
        image: "",
        description: "",
        price: "",
        open: true,
        time: "10:05",

        productMessages: [

            {
                id: "pm-1",
                side: "left",
                text: "あいうえお",
                time: "10:06"
            },

            {
                id: "pm-2",
                side: "right",
                text: "かきくけこ",
                time: "10:07"
            },

            {
                id: "pm-3",
                side: "left",
                text: "さしすせそ",
                time: "10:08"
            },

            {
                id: "pm-4",
                side: "right",
                text: "たちつてと",
                time: "10:09"
            }

        ]
    },

    {
        id: "text-5",
        type: "text",
        side: "left",
        text: "なにぬねの",
        time: "10:10"
    },

    {
        id: "text-6",
        type: "text",
        side: "right",
        text: "はひふへほ",
        time: "10:11"
    }

];


/*
 * データを複製する
 */
function deepCopy(value) {
    return JSON.parse(JSON.stringify(value));
}


/*
 * データごとのIDを作る
 */
function makeId() {

    if (
        window.crypto &&
        typeof window.crypto.randomUUID === "function"
    ) {
        return window.crypto.randomUUID();
    }

    return "id-" + Date.now() + "-" +
        Math.random().toString(16).slice(2);
}


/*
 * 現在時刻を取得する
 */
function getTime() {

    return new Date().toLocaleTimeString("ja-JP", {
        hour: "2-digit",
        minute: "2-digit"
    });

}


/*
 * ブラウザに保存されているデータを読み込む
 */
function loadItems() {

    try {

        const saved = localStorage.getItem(STORAGE_KEY);

        if (saved) {

            const parsed = JSON.parse(saved);

            if (Array.isArray(parsed)) {
                return parsed;
            }

        }

    } catch (error) {

        console.warn(
            "保存済みデータを読み込めませんでした。",
            error
        );

    }

    return deepCopy(initialItems);
}


/*
 * 現在のメッセージデータ
 */
let items = loadItems();


/*
 * データをブラウザに保存する
 */
function saveItems() {

    try {

        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(items)
        );

        return true;

    } catch (error) {

        alert("ブラウザにデータを保存できませんでした。");

        return false;

    }

}


/*
 * 通常メッセージをHTML要素として作成する
 *
 * textContent を使うことで、
 * 入力されたHTMLが実行されないようにします。
 */
function renderTextMessage(item) {

    const row = document.createElement("div");

    row.className =
        "message " +
        (item.side === "right" ? "right" : "left");

    row.textContent = item.text || "";

    return row;

}


/*
 * 商品カードを作成する
 */
function renderProductCard(item) {

    const card = document.createElement("section");

    card.className =
        "product-card" +
        (item.open ? " open" : "");

    card.dataset.productId = item.id;


    /*
     * 商品カードの見出し
     */
    const header = document.createElement("div");

    header.className = "product-header";

    header.tabIndex = 0;

    header.setAttribute("role", "button");

    header.setAttribute(
        "aria-expanded",
        item.open ? "true" : "false"
    );


    const arrow = document.createElement("span");

    arrow.className = "arrow-icon";

    arrow.textContent = "▶";


    const summary = document.createElement("div");

    summary.className = "product-summary";


    const title = document.createElement("div");

    title.textContent =
        item.name || "指定した商品の情報";


    const subtitle = document.createElement("div");

    subtitle.className = "product-subtitle";

    subtitle.textContent =
        item.subtitle || "商品情報";


    summary.appendChild(title);

    summary.appendChild(subtitle);

    header.appendChild(arrow);

    header.appendChild(summary);


    /*
     * 商品カードの展開部分
     */
    const detail = document.createElement("div");

    detail.className = "accordion-content";


    /*
     * 商品画像
     */
    const imageURL = safeImageURL(item.image);

    if (imageURL) {

        const image = document.createElement("img");

        image.className = "product-image visible";

        image.src = imageURL;

        image.alt = item.name || "商品画像";

        image.loading = "lazy";

        detail.appendChild(image);

    }


    /*
     * 商品価格
     */
    if (
        item.price !== "" &&
        Number.isFinite(Number(item.price))
    ) {

        const price = document.createElement("div");

        price.className = "product-price";

        price.textContent =
            "価格：¥" +
            Number(item.price).toLocaleString("ja-JP");

        detail.appendChild(price);

    }


    /*
     * 商品説明
     */
    if (item.description) {

        const description = document.createElement("div");

        description.className = "product-description";

        description.textContent = item.description;

        detail.appendChild(description);

    }


    /*
     * 商品専用チャットの見出し
     */
    const chatTitle = document.createElement("div");

    chatTitle.className = "product-chat-title";

    chatTitle.textContent = "この商品についてのメッセージ";

    detail.appendChild(chatTitle);


    /*
     * 商品専用メッセージ一覧
     */
    const messages = document.createElement("div");

    messages.className = "product-messages";

    messages.setAttribute("aria-live", "polite");


    if (
        !Array.isArray(item.productMessages) ||
        item.productMessages.length === 0
    ) {

        const empty = document.createElement("div");

        empty.className = "empty-message";

        empty.textContent =
            "この商品のメッセージはまだありません。";

        messages.appendChild(empty);

    } else {

        item.productMessages.forEach(function(message) {

            messages.appendChild(
                renderTextMessage(message)
            );

        });

    }

    detail.appendChild(messages);


    /*
     * 商品専用の入力欄
     */
    const form = document.createElement("form");

    form.className = "input-group product-form";

    form.dataset.productId = item.id;


    const input = document.createElement("input");

    input.type = "text";

    input.maxLength = 2000;

    input.placeholder = "メッセージを入力";

    input.setAttribute(
        "aria-label",
        "商品専用メッセージ"
    );

    input.autocomplete = "off";


    const button = document.createElement("button");

    button.type = "submit";

    button.textContent = "送信";


    form.appendChild(input);

    form.appendChild(button);

    detail.appendChild(form);


    /*
     * 商品カードをクリックすると開閉する
     */
    header.addEventListener("click", function() {

        toggleProduct(item.id);

    });


    /*
     * キーボードでも開閉できる
     */
    header.addEventListener("keydown", function(event) {

        if (
            event.key === "Enter" ||
            event.key === " "
        ) {

            event.preventDefault();

            toggleProduct(item.id);

        }

    });


    card.appendChild(header);

    card.appendChild(detail);

    return card;

}


/*
 * 画像URLの確認
 *
 * http または https のURLだけを使用します。
 */
function safeImageURL(value) {

    if (!value) {
        return "";
    }

    try {

        const url = new URL(value);

        if (
            url.protocol === "https:" ||
            url.protocol === "http:"
        ) {
            return url.href;
        }

        return "";

    } catch {

        return "";

    }

}


/*
 * 画面全体を再描画する
 */
function render() {

    mainMessages.replaceChildren();

    items.forEach(function(item) {

        if (item.type === "product") {

            mainMessages.appendChild(
                renderProductCard(item)
            );

        } else {

            mainMessages.appendChild(
                renderTextMessage(item)
            );

        }

    });

}


/*
 * 商品カードを開閉する
 */
function toggleProduct(productId) {

    const product = items.find(function(item) {

        return (
            item.id === productId &&
            item.type === "product"
        );

    });

    if (!product) {
        return;
    }

    product.open = !product.open;

    saveItems();

    render();

}


/*
 * 通常チャットの送信処理
 */
document
    .getElementById("mainForm")
    .addEventListener("submit", function(event) {

        event.preventDefault();

        const input =
            document.getElementById("mainInput");

        const text = input.value.trim();

        if (!text) {

            input.focus();

            return;

        }


        items.push({

            id: makeId(),

            type: "text",

            side: "right",

            text: text,

            time: getTime()

        });


        if (saveItems()) {

            input.value = "";

            render();

            input.focus();

        } else {

            items.pop();

        }

    });


/*
 * 商品専用チャットの送信処理
 *
 * 商品カードが複数あっても、
 * 送信したカードの履歴だけに追加します。
 */
mainMessages.addEventListener(
    "submit",
    function(event) {

        const form =
            event.target.closest(".product-form");

        if (!form) {
            return;
        }

        event.preventDefault();


        const productId = form.dataset.productId;

        const input = form.querySelector("input");

        const text = input.value.trim();


        if (!text) {

            input.focus();

            return;

        }


        /*
         * 送信先の商品カードを探す
         */
        const product = items.find(function(item) {

            return (
                item.id === productId &&
                item.type === "product"
            );

        });


        if (!product) {
            return;
        }


        if (!Array.isArray(product.productMessages)) {

            product.productMessages = [];

        }


        /*
         * この商品の履歴にだけ追加
         */
        product.productMessages.push({

            id: makeId(),

            side: "right",

            text: text,

            time: getTime()

        });


        /*
         * 送信した商品カードを開いた状態にする
         */
        product.open = true;


        if (saveItems()) {

            render();


            /*
             * 再描画後、同じ商品の入力欄にフォーカスする
             */
            const cards =
                mainMessages.querySelectorAll(
                    ".product-card"
                );

            cards.forEach(function(card) {

                if (
                    card.dataset.productId === productId
                ) {

                    const newInput =
                        card.querySelector(
                            ".product-form input"
                        );

                    if (newInput) {
                        newInput.focus();
                    }

                }

            });

        } else {

            product.productMessages.pop();

        }

    }
);


/*
 * 最初の画面を表示
 */
render();

</script>

<?php
// Meetory共通フッターを読み込む
require_once '../../meetory-footer.php';
?>