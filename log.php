<?php
ob_start();
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'log';
$_SESSION['adminpage'] = true;

include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));
requirePermission('perm_showLog');

$logChunkLimit = listChunkLogConfiguredLimit();
ob_start();
$chunk = listChunkLog(0, $logChunkLimit);
$leak = ob_get_clean();
if($leak !== false && $leak !== '') {
    $chunk['html'] = $leak.$chunk['html'];
}

include 'common/header.php';
?>
<?php
adminListPageBegin('System', 'Log');
adminListSearchField('Log durchsuchen…');
?>
<div id="Liste" style="clear:both;">
<?php echo $chunk['html']; ?>
<?php echo listChunkRenderSentinel('log', $chunk['nextCursor'], $chunk['hasMore'], 'filterLog', ' data-limit="'.(int)$logChunkLimit.'" data-server-q="1"'); ?>
</div>
<?php adminListPageEnd(); ?>
<script>
function getLogTopRow() {
    var parent = document.getElementById("Liste");
    if(!parent) return null;
    return parent.querySelector(":scope > div[id]:not(#listSentinel)");
}

function getLogMaxIndex() {
    var parent = document.getElementById("Liste");
    if(!parent) return 0;
    var rows = parent.querySelectorAll(":scope > div[id]:not(#listSentinel)");
    if(!rows.length) return 0;
    var max = 0;
    for(var i = 0; i < rows.length; i++) {
        var n = parseInt(rows[i].id, 10);
        if(n > max) max = n;
    }
    return max;
}

function getLogTopTimestamp() {
    var top = getLogTopRow();
    if(!top) return '';
    return top.getAttribute('data-timestamp') || '';
}

function getLogPollLimit() {
    var sentinel = document.getElementById("listSentinel");
    if(!sentinel) return 0;
    var n = parseInt(sentinel.getAttribute("data-limit") || "0", 10);
    return n > 0 ? n : 0;
}

function getLog() {
    var maxIndex = getLogMaxIndex();
    if(!(maxIndex > 0)) return;
    var topTimestamp = getLogTopTimestamp();
    var limit = getLogPollLimit();

    var xmlhttp;
    if (window.XMLHttpRequest) {
	xmlhttp=new XMLHttpRequest();
    }
    else {
	xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange=function() {
	if (xmlhttp.readyState==4 && xmlhttp.status==200 && xmlhttp.responseText) {
            var parent = document.getElementById("Liste");
            if(!parent) return;
            var doc = new DOMParser().parseFromString(xmlhttp.responseText, 'text/html');
            var nodes = [];
            for(var c = doc.body.firstElementChild; c; c = c.nextElementSibling) {
                if(c.id) nodes.push(c);
            }
            if(!nodes.length) return;

            var frag = document.createDocumentFragment();
            var hasNew = false;
            for(var i = 0; i < nodes.length; i++) {
                var div = nodes[i];
                var existing = document.getElementById(div.id);
                if(existing) {
                    existing.parentNode.replaceChild(div, existing);
                }
                else {
                    frag.appendChild(div);
                    hasNew = true;
                }
            }
            if(hasNew) {
                var first = parent.querySelector(":scope > div[id]:not(#listSentinel)");
                if(first) {
                    parent.insertBefore(frag, first);
                }
                else {
                    var sentinel = document.getElementById("listSentinel");
                    if(sentinel) parent.insertBefore(frag, sentinel);
                    else parent.appendChild(frag);
                }
            }
            var filterInput = document.getElementById("filterString");
            if(filterInput && filterInput.value && typeof filterLog === "function") {
                filterLog();
            }
	}
    }
    var body = "maxIndex="+encodeURIComponent(maxIndex)
        +"&topTimestamp="+encodeURIComponent(topTimestamp)
        +"&limit="+encodeURIComponent(limit);
    xmlhttp.open("POST", "getLog.php", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send(body);
}
var interval = setInterval(getLog, 1000);
</script>

<script src="<?php echo assetUrl('js/filterLog.js'); ?>"></script>
<script src="<?php echo assetUrl('js/infiniteScroll.js'); ?>"></script>

<?php
include 'common/footer.php';
?>
