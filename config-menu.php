<?php
ob_start();
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'config';
$_SESSION['adminpage'] = true;

include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));
requirePermission('perm_editConfig');

if(isset($_POST['save'])) {
    if(!csrf_verify(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        denyAccess('Ungültiges Sicherheits-Token. Bitte Seite neu laden und erneut speichern.');
    }
    $sql = sprintf('SELECT * FROM `%sconfig`;', $GLOBALS['dbprefix']);
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        if($row['Type'] === 'internal' || mitIsHiddenConfigParam($row['Parameter'])) {
            continue;
        }
        switch($row['Type']) {
        case 'days':
            $val = 0;
            for($i = 1; $i <= 7; $i++) {
                if(isset($_POST[$row['Parameter'].$i])) {
                    $val += 2 ** ($i - 1);
                }
            }
            if($val == $row['Value']) {
                break;
            }
            $sql = sprintf(
                'UPDATE `%sconfig` SET `Value` = "%s" WHERE `Parameter` = "%s";',
                $GLOBALS['dbprefix'],
                $val,
                $row['Parameter']
            );
            $dbr2 = mysqli_query($conn, $sql);
            sqlerror();
            if($dbr2) {
                logConfigChange($row['Parameter'], $row['Value'], $val, $row['Type']);
            }
            break;
        case 'bool':
        case 'uint':
        case 'int':
        case 'time':
        case 'string':
        case 'email':
        case 'text':
            if(isset($_POST[$row['Parameter']])) {
                $newVal = $_POST[$row['Parameter']];
                if($newVal == $row['Value']) {
                    break;
                }
                $sql = sprintf(
                    'UPDATE `%sconfig` SET `Value` = "%s" WHERE `Parameter` = "%s";',
                    $GLOBALS['dbprefix'],
                    mysqli_real_escape_string($conn, $newVal),
                    $row['Parameter']
                );
                $dbr2 = mysqli_query($conn, $sql);
                sqlerror();
                if($dbr2) {
                    logConfigChange($row['Parameter'], $row['Value'], $newVal, $row['Type']);
                }
            }
            break;
        case 'color':
            // Farben werden per AJAX (savePara.php) gespeichert
            break;
        default:
            if(isset($_POST[$row['Parameter']])) {
                $newVal = $_POST[$row['Parameter']];
                if($newVal == $row['Value']) {
                    break;
                }
                $sql = sprintf(
                    'UPDATE `%sconfig` SET `Value` = "%s" WHERE `Parameter` = "%s";',
                    $GLOBALS['dbprefix'],
                    mysqli_real_escape_string($conn, $newVal),
                    $row['Parameter']
                );
                $dbr2 = mysqli_query($conn, $sql);
                sqlerror();
                if($dbr2) {
                    logConfigChange($row['Parameter'], $row['Value'], $newVal, $row['Type']);
                }
            }
            break;
        }
    }
    // Reload options so the form shows saved values / colors
    $optionsDB = loadconfig();
    $GLOBALS['optionsDB'] = $optionsDB;
}

include 'common/header.php';
?>
<script>
function savePara(Parameter, Value, reload) {
	if (window.XMLHttpRequest) {
	    xmlhttp=new XMLHttpRequest();
	}
	else {
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function() {
	    if(xmlhttp.readyState === 4) {
	        if(xmlhttp.status === 200 && xmlhttp.responseText === 'ok') {
	            if(reload) window.location.reload();
	        }
	        else if(reload) {
	            if(typeof appAlert === 'function') {
	                appAlert('Farbschema konnte nicht übernommen werden: ' + xmlhttp.responseText);
	            } else {
	                alert('Farbschema konnte nicht übernommen werden: ' + xmlhttp.responseText);
	            }
	        }
	    }
	};
	var body = "cmd=change&para="+encodeURIComponent(Parameter)+"&value="+encodeURIComponent(Value);
	xmlhttp.open("POST", "savePara.php", true);
	xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	xmlhttp.send(body);
}
function clearColor(Parameter, inputId, labelId) {
    savePara(Parameter, '', false);
    var input = document.getElementById(inputId);
    if(input) input.value = '#808080';
    var label = document.getElementById(labelId);
    if(label) label.textContent = '(keine)';
}
function applyColorScheme(id) {
    savePara('colorSchemeActive', id, true);
}
function renameColorScheme(name) {
    if (window.XMLHttpRequest) {
	    xmlhttp=new XMLHttpRequest();
	}
	else {
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	var body = "cmd=schemeName&value="+encodeURIComponent(name);
	xmlhttp.open("POST", "savePara.php", true);
	xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	xmlhttp.send(body);
}
function resetColorScheme() {
    var run = function() {
        if (window.XMLHttpRequest) {
            xmlhttp=new XMLHttpRequest();
        }
        else {
            xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function() {
            if(xmlhttp.readyState === 4) {
                window.location.reload();
            }
        };
        var body = "cmd=schemeReset";
        xmlhttp.open("POST", "savePara.php", true);
        xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xmlhttp.send(body);
    };
    if(typeof appConfirm === 'function') {
        appConfirm('Aktives Farbschema auf Werkseinstellung zurücksetzen?').then(function(ok) {
            if(ok) run();
        });
        return;
    }
    if(!confirm('Aktives Farbschema auf Werkseinstellung zurücksetzen?')) return;
    run();
}
</script>
<?php
adminListPageBegin('System', 'globale Einstellungen');
$hover = isset($GLOBALS['optionsDB']['HoverEffect']) && $GLOBALS['optionsDB']['HoverEffect'] !== ''
    ? $GLOBALS['optionsDB']['HoverEffect']
    : 'w3-hover-gray';
?>
<div class="w3-container w3-card w3-margin-bottom w3-padding <?php echo $GLOBALS['optionsDB']['colorWarning']; ?>">
  <div class="w3-col l3 m3 s2 w3-center">
    <i class="fas fa-exclamation-triangle"></i>
</div>
  <div class="w3-col l6 m6 s8 w3-center">
<b>Achtung, &Auml;nderungen dieser Einstellungen wirken sich auf alle Nutzer aus.</b>
</div>

  <div class="w3-col l3 m3 s2 w3-center">
    <i class="fas fa-exclamation-triangle"></i>
</div>
</div>
<?php
ensureColorSchemesStored();
$colorSchemes = loadColorSchemes();
$activeSchemeId = getActiveColorSchemeId();
$activeSchemeName = isset($colorSchemes[$activeSchemeId]['name'])
    ? $colorSchemes[$activeSchemeId]['name']
    : $activeSchemeId;
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit']) ? $GLOBALS['optionsDB']['colorBtnEdit'] : '';
?>
<div class="w3-container w3-card w3-margin w3-padding">
  <div class="w3-row w3-padding">
    <div class="w3-col l3 m4 s12"><b>Farbschema</b></div>
    <div class="w3-col l5 m4 s12">Fünf editierbare Schemata. Auswahl übernimmt alle Farben; Änderungen an Einzelfarben speichern ins aktive Schema.</div>
    <div class="w3-col l4 m4 s12 w3-center">
      <select class="w3-select w3-border w3-margin-bottom" id="colorSchemeSelect"
              onchange="applyColorScheme(this.value)">
<?php foreach($colorSchemes as $sid => $scheme) {
    $sel = ($sid === $activeSchemeId) ? ' selected' : '';
    $label = isset($scheme['name']) ? $scheme['name'] : $sid;
    echo '        <option value="'.htmlspecialchars($sid, ENT_QUOTES, 'UTF-8').'"'.$sel.'>'
        .htmlspecialchars($label, ENT_QUOTES, 'UTF-8')."</option>\n";
} ?>
      </select>
      <label class="w3-small">Name des aktiven Schemas</label>
      <input class="w3-input w3-border w3-margin-bottom" type="text" id="colorSchemeName"
             value="<?php echo htmlspecialchars($activeSchemeName, ENT_QUOTES, 'UTF-8'); ?>"
             onchange="renameColorScheme(this.value)" />
      <button type="button" class="w3-button w3-small <?php echo htmlspecialchars($btnEdit, ENT_QUOTES, 'UTF-8'); ?>"
              onclick="resetColorScheme()">Schema zurücksetzen</button>
    </div>
  </div>
</div>
<form action="config-menu.php" method="POST">
<?php echo csrf_field(); ?>
<div class="w3-container w3-padding w3-border-bottom w3-border-black">
    <div class="w3-col l3"><b>Parameter</b></div>
    <div class="w3-col l5"><b>Beschreibung</b></div>
    <div class="w3-col l4 w3-center"><b>Wert</b></div>
</div>
<?php
$sql = sprintf('SELECT * FROM `%sconfig` ORDER BY `Parameter`;', $GLOBALS['dbprefix']);
$dbr = mysqli_query($conn, $sql);
sqlerror();
while($row = mysqli_fetch_array($dbr)) {
    if($row['Type'] === 'internal' || mitIsHiddenConfigParam($row['Parameter'])) {
        continue;
    }
    echo '<div class="w3-container w3-padding w3-border-bottom w3-boder-black '.htmlspecialchars($hover, ENT_QUOTES, 'UTF-8').'">'."\n";
    echo '<div class="w3-col l3 m3 s12"><b>'.htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8').'</b></div>'
        .'<div class="w3-col l5 m5 s12">'.htmlspecialchars($row['Description'], ENT_QUOTES, 'UTF-8')."</div>\n";
    switch($row['Type']) {
    case 'bool':
        echo '<div class="w3-col l4 m4 s12 w3-center">nein&nbsp;&nbsp;'."\n";
        echo '<input type="radio" name="'.htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8').'" value="0" ';
        if($row['Value'] == 0) echo 'checked';
        echo '/>&nbsp;&nbsp;'."\n";
        echo '<input type="radio" name="'.htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8').'" value="1" ';
        if($row['Value'] == 1) echo 'checked';
        echo '/>&nbsp;&nbsp;'."\n";
        echo '&nbsp;&nbsp;ja</div>'."\n";
        break;
    case 'days':
        $dows = array('Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So');
        echo '<div class="w3-col l4 m4 s12 w3-center">'."\n";
        $c = array('', '', '', '', '', '', '');
        $v = (int)$row['Value'];
        for($i = 7; $i >= 1; $i--) {
            if($v / 2 ** ($i - 1) >= 1) {
                $c[$i - 1] = 'checked';
                $v = $v - 2 ** ($i - 1);
            }
        }
        for($i = 1; $i <= 7; $i++) {
            echo $dows[$i - 1].'&nbsp;<input type="checkbox" name="'
                .htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8').$i
                .'" value="'.$i.'" '.$c[$i - 1].'/>&nbsp;'."\n";
        }
        echo '</div>'."\n";
        break;
    case 'time':
        echo '<input class="w3-col l4 m4 s12 w3-center" type="time" name="'
            .htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8')
            .'" value="'.htmlspecialchars($row['Value'], ENT_QUOTES, 'UTF-8').'" />'."\n";
        break;
    case 'int':
        echo '<input class="w3-col l4 m4 s12 w3-center" type="number" name="'
            .htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8')
            .'" value="'.htmlspecialchars($row['Value'], ENT_QUOTES, 'UTF-8').'" />'."\n";
        break;
    case 'uint':
        echo '<input class="w3-col l4 m4 s12 w3-center" type="number" min="0" name="'
            .htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8')
            .'" value="'.htmlspecialchars($row['Value'], ENT_QUOTES, 'UTF-8').'" />'."\n";
        break;
    case 'text':
        echo '<textarea class="w3-col l4 m4 s12" rows="10" cols="30" name="'
            .htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8').'">'
            .htmlspecialchars($row['Value'], ENT_QUOTES, 'UTF-8').'</textarea>'."\n";
        break;
    case 'string':
        echo '<input class="w3-col l4 m4 s12" type="text" name="'
            .htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8')
            .'" value="'.htmlspecialchars($row['Value'], ENT_QUOTES, 'UTF-8').'" />'."\n";
        break;
    case 'email':
        echo '<input class="w3-col l4 m4 s12" type="email" name="'
            .htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8')
            .'" value="'.htmlspecialchars($row['Value'], ENT_QUOTES, 'UTF-8').'" />'."\n";
        break;
    case 'color':
        $raw = (string)$row['Value'];
        $picker = colorPickerValue($raw);
        $display = ($raw === '') ? '(keine)' : $raw;
        $safePara = htmlspecialchars($row['Parameter'], ENT_QUOTES, 'UTF-8');
        $inputId = 'color_'.$row['Parameter'];
        $labelId = 'colorlabel_'.$row['Parameter'];
        echo '<div class="w3-col l4 m4 s12 w3-center">'."\n";
        echo '<span id="'.$labelId.'" class="w3-small">'.htmlspecialchars($display, ENT_QUOTES, 'UTF-8').'</span><br>'."\n";
        echo '<input id="'.$inputId.'" type="color" value="'.htmlspecialchars($picker, ENT_QUOTES, 'UTF-8').'" '
            .'onchange="savePara(\''.$safePara.'\', this.value, false); document.getElementById(\''.$labelId.'\').textContent=this.value;" '
            .'style="width:3.5em;height:2.2em;padding:0;border:1px solid #000;vertical-align:middle;" />'."\n";
        echo '&nbsp;<button type="button" class="w3-button w3-small '.htmlspecialchars($btnEdit, ENT_QUOTES, 'UTF-8').'" '
            .'onclick="clearColor(\''.$safePara.'\', \''.$inputId.'\', \''.$labelId.'\')">keine Farbe</button>'."\n";
        echo '</div>'."\n";
        break;
    default:
        echo '<div class="w3-col l4 w3-center">kein Typ spezifiziert.</div>'."\n";
        break;
    }
    echo '</div>';
}
?>
<button class="w3-btn w3-padding <?php echo htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8'); ?> w3-border w3-margin w3-mobile" type="submit" name="save" value="speichern">Speichern</button>
</form>

<?php
adminListPageEnd();
include 'common/footer.php';
?>
