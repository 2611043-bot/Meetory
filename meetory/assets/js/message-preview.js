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
