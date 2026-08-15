/**
 * Client list filter for MIT admin lists (#Liste .list-row).
 * UI-SHELL: listRowSearchText / listRowMatchesQuery (AND tokens).
 */
function filterListRows() {
    var input = document.getElementById('filterString');
    var filter = input && input.value ? String(input.value) : '';
    var list = document.getElementById('Liste');
    if (!list) {
        return;
    }
    var rows = list.querySelectorAll('.list-row');
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var text = (typeof listRowSearchText === 'function')
            ? listRowSearchText(row)
            : (row.getAttribute('data-search') || row.textContent || '');
        var match = (typeof listRowMatchesQuery === 'function')
            ? listRowMatchesQuery(text, filter)
            : (!String(filter).trim() || String(text).toUpperCase().indexOf(String(filter).trim().toUpperCase()) > -1);
        if (match) {
            row.classList.remove('list-filtered-out');
        } else {
            row.classList.add('list-filtered-out');
        }
    }
}
