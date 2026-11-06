const searchInput = document.getElementById('talkSearch');
const talkItems = document.querySelectorAll('.talk-item');
const talkEmpty = document.getElementById('talkEmpty');

searchInput.addEventListener('input', function () {

    const keyword = this.value.trim().toLowerCase();
    let visibleCount = 0;

    talkItems.forEach(function (item) {

        const userName = item.dataset.name.toLowerCase();

        const isMatch = userName.includes(keyword);

        item.style.display = isMatch ? 'flex' : 'none';

        if (isMatch) {
            visibleCount++;
        }
    });

    talkEmpty.hidden = visibleCount !== 0;

});