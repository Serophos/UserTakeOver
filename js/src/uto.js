export default function uto($) {
    return {
        init(selector) {
            $(selector).find("input[data-role='uto-search-input-field']").each(function () {
                const field = $(this);
                if (field.data('uto-initialized')) {
                    return;
                }
                field.data('uto-initialized', true);
                field.on('receivedata', function (_event, autocomplete, results) {
                    // The library interpolates HTML. Treat names as text and IDs as integers.
                    autocomplete.json = (Array.isArray(results) ? results : [])
                        .filter(user => /^\d+$/.test(String(user.value)))
                        .map(user => ({
                            value: String(user.value),
                            display: $('<div>').text(user.display).html()
                        }));
                });
                field.tinyAutocomplete({
                    wrapClasses: 'uto-search-field',
                    closeOnSelect: false,
                    queryProperty: 'usrto_search_term',
                    url: field.data('action'),
                    method: 'get',
                    minChars: 3,
                    keyboardDelay: 400,
                    itemTemplate: '<li class="autocomplete-item"><a class="il-link link-bulky" '
                        + 'href="goto.php?track=1&target=usrto&usr_id={{value}}">'
                        + '<span class="bulky-label">{{display}}</span></a></li>',
                    showNoResults: true,
                    noResultsTemplate: '<li class="autocomplete-empty"><span class="bulky-label">...</span></li>',
                    onSelect(item) {
                        $(item).find('a').get(0)?.click();
                    }
                });
            });
        },
        labelMembers(id, labels) {
            const container = document.getElementById(id);
            if (!container) {
                return;
            }
            const applyLabels = () => {
                container.querySelectorAll('.tagify__tag[value]').forEach(tag => {
                    const label = labels[tag.getAttribute('value')];
                    const text = tag.querySelector('.tagify__tag-text');
                    if (label && text && text.textContent !== label) {
                        text.textContent = label;
                    }
                });
            };
            new MutationObserver(applyLabels).observe(container, {childList: true, subtree: true});
            applyLabels();
        }
    };
}
