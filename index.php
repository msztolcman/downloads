<?php

error_reporting (E_ALL|E_STRICT);
ini_set ('display_errors', 1);
session_start ();

define ('ABSOLUTE_URI', 'http://filmy.urzenia.net');

function debug_array () {
    echo '<pre>';
    foreach (func_get_args () as $arg) {
        print_r ($arg);
        print "\n\n";
    }
    echo '</pre>';
    return;
}

function sql_connect ($dbname='/films.db.sqlite') {
    $sql = new PDO ('sqlite:'.$dbname);
    $sql->setAttribute (PDO::ATTR_CASE, PDO::CASE_NATURAL);
    $sql->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $sql;
}
$sql = sql_connect ();

function str_entit ($s) {
    return strtr ($s,
        array (
            '&' => '&amp',
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
            "'" => '&#39;'
        )
    );
}

$film_data = array (
    'id'        => '',
    'name'      => '',
    'title'     => '',
    'imdb_rate' => '',
    'notes'     => '',
    'status'    => 'download'
);

$film_statuses = array (
    'download'  => 'pobierany',
    'wanted'    => 'pożądany',
    'done'      => 'pobrany'
);

$data_types = array (
    'undef'     => 'b.d.',
    'movie'     => 'film',
    'serie'     => 'serial',
    'game'      => 'gra',
    'app'       => 'aplikacja',
    'doc'       => 'dokument',
    'music'     => 'muzyka',
);

$errors = array ();

if (isset ($_GET['action'])) {
    switch ($_GET['action']) {
        case 'createdb':
            try {
                $GLOBALS['sql']->exec ('CREATE TABLE `films` (
                    `flm_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `flm_name` TEXT NOT NULL DEFAULT "",
                    `flm_title` TEXT NOT NULL DEFAULT "",
                    `flm_imdb_rate` INTEGER NOT NULL DEFAULT 0,
                    `flm_rate` INTEGER NOT NULL DEFAULT 0,
                    `flm_notes` TEXT NOT NULL DEFAULT "",
                    `flm_person` TEXT NOT NULL DEFAULT "",
                    `flm_status` TEXT NOT NULL DEFAULT "",
                    `flm_status_person` TEXT NOT NULL DEFAULT "",
                    `flm_dateadd` TEXT NOT NULL DEFAULT ""
                )');
            } catch (PDOException $e) {
                debug_array ($e);
                exit;
            }
            header ('Location: '.ABSOLUTE_URI);
            exit;
        break;

        case 'delete':
            if (isset ($_GET['flm_id']) && is_numeric ($_GET['flm_id']) && $_GET['flm_id'] > 0) {
                $query = $GLOBALS['sql']->prepare ('DELETE FROM `films` WHERE `flm_id` = ?');
                $query->execute (array ($_GET['flm_id']));
                header ('Location: '.ABSOLUTE_URI);
                exit;
            }
            else {
                $errors[] = 'Parametr "flm_id" ma niewłaściwą wartość.';
            }
        break;

        case 'add':
            if (!isset ($_POST['film_name']) || !$_POST['film_name']) {
                $errors[] = 'brak nazwy pliku';
            }
            if (
                isset ($_POST['film_imdb_rate']) && $_POST['film_imdb_rate'] &&
                (!is_numeric ($_POST['film_imdb_rate']) || $_POST['film_imdb_rate'] < 1 || $_POST['film_imdb_rate'] > 10)
            ) {
                $errors[] = '"ocena w IMDB" musi być wartością liczbową z zakresu od 1 do 10';
            }
            if (isset ($_POST['film_status']) && !in_array ($_POST['film_status'], array ('download', 'wanted', 'done'))) {
                $errors[] = 'niewłaściwy status filmu';
            }

            if (isset ($_POST['flm_id']) && (!is_numeric ($_POST['flm_id']) || $_POST['flm_id'] <= 0)) {
                $errors[] = 'Parametr "flm_id" ma niewłaściwą wartość.';
            }

            if (!count ($errors)) {
                try {
                    if (isset ($_POST['flm_id'])) {
                        if (is_numeric ($_POST['flm_id']) && $_POST['flm_id'] > 0) {
                            $query = $GLOBALS['sql']->prepare ('
                                UPDATE
                                    `films`
                                SET
                                    `flm_name` = ?,
                                    `flm_title` = ?,
                                    `flm_imdb_rate` = ?,
                                    `flm_notes` = ?,
                                    `flm_status` = ?,
                                    `data_type` = ?
                                WHERE
                                    `flm_id` = ?'
                            );

                            $query->execute (array (
                                $_POST['film_name'],
                                (isset ($_POST['film_title'])       ? $_POST['film_title']      : ''),
                                (isset ($_POST['film_imdb_rate'])   ? $_POST['film_imdb_rate']  : ''),
                                (isset ($_POST['film_notes'])       ? $_POST['film_notes']      : ''),
                                (isset ($_POST['film_status'])      ? $_POST['film_status']     : 'download'),
                                (isset ($_POST['film_type'])        ? $_POST['film_type']       : 'undef'),
                                $_POST['flm_id']
                            ));
                        }

                        else {
                            $errors[] = 'Parametr "flm_id" ma niewłaściwą wartość.';
                        }
                    }

                    else {
                        $query = $GLOBALS['sql']->prepare ('
                            INSERT INTO
                                `films` (`flm_name`, `flm_title`, `flm_imdb_rate`, `flm_rate`,
                                `flm_notes`, `flm_person`, `flm_status`, `flm_status_person`,
                                `flm_dateadd`, `data_type`)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, strftime ("%Y-%m-%d %H:%M:%S", "now"), ?)');

                        $query->execute (array (
                            $_POST['film_name'],
                            (isset ($_POST['film_title'])       ? $_POST['film_title']      : ''),
                            (isset ($_POST['film_imdb_rate'])   ? $_POST['film_imdb_rate']  : ''),
                            '',
                            (isset ($_POST['film_notes'])       ? $_POST['film_notes']      : ''),
                            (isset ($_SERVER['REMOTE_USER'])    ? $_SERVER['REMOTE_USER']   : ''),
                            (isset ($_POST['film_status'])      ? $_POST['film_status']     : 'download'),
                            (isset ($_SERVER['REMOTE_USER'])    ? $_SERVER['REMOTE_USER']   : ''),
                            (isset ($_POST['film_type'])        ? $_POST['film_type']       : 'undef'),
                        ));
                    }
                }
                catch (PDOException $e) {
                    debug_array ($e);
                    exit;
                }

                header ('Location: '.ABSOLUTE_URI);
                exit;
            }
            else {
                $film_data = array (
                    'name'      => str_entit ($_POST['film_name']),
                    'title'     => str_entit ($_POST['film_title']),
                    'imdb_rate' => str_entit ($_POST['film_imdb_rate']),
                    'notes'     => str_entit ($_POST['film_notes']),
                    'status'    => str_entit ($_POST['film_status'])
                );
            }
        break;

        case 'cycle_status':
            if (isset ($_GET['flm_id']) && is_numeric ($_GET['flm_id']) && $_GET['flm_id'] > 0) {
                $query = $GLOBALS['sql']->prepare ('SELECT `flm_status` FROM `films` WHERE `flm_id` = ?');
                $query->execute (array ($_GET['flm_id']));
                $cur_status = $query->fetch (PDO::FETCH_ASSOC);
                $cur_status = $cur_status['flm_status'];

                $next_status = $cur_status;
                switch ($cur_status) {
                    case 'download':    $next_status = 'done';      break;
                    case 'wanted':      $next_status = 'download';  break;
                }
                if ($cur_status != $next_status) {
                    $query = $GLOBALS['sql']->prepare ('
                        UPDATE
                            `films`
                        SET
                            `flm_status`        = ?,
                            `flm_status_person` = ?
                        WHERE
                            `flm_id` = ?');
                    $query->execute (array (
                        $next_status,
                        (isset ($_SERVER['REMOTE_USER'])    ? $_SERVER['REMOTE_USER']   : ''),
                        $_GET['flm_id']
                    ));
                }

                header ('Location: '.ABSOLUTE_URI);
                exit;
            }
            else {
                $errors[] = 'Parametr "flm_id" ma niewłaściwą wartość.';
            }
        break;

    }
}


# sortowanie - jak ja tego nie lubię ;)
if (
    isset ($_GET['sort']) && isset ($_GET['order']) &&
    in_array ($_GET['sort'], array ('id', 'name', 'title', 'imdb_rate', 'person', 'status', 'dateadd', 'type')) &&
    in_array ($_GET['order'], array ('asc', 'desc'))
) {
    $sort       = ($_GET['sort'] == 'type' ? 'data_' : 'flm_').$_GET['sort'];
    $order      = $_GET['order'];
}

else if (isset ($_SESSION['sort']) && isset ($_SESSION['order'])) {
    $sort       = $_SESSION['sort'];
    $order      = $_SESSION['order'];
}

else {
    $sort       = 'flm_id';
    $order      = 'asc';
}

$_SESSION['sort']   = $sort;
$_SESSION['order']  = $order;
$order_rev          = $order == 'asc' ? 'desc' : 'asc';

if (isset ($_GET['action']) && $_GET['action'] == 'filter' && isset ($_POST['filter_data_type'])) {
    $_SESSION['filter_data_type'] = array ();

    if (!isset ($_SESSION['filter_data_type']['none'])) {
        foreach ($_POST['filter_data_type'] as $ftype) {
            if (isset ($data_types[$ftype])) {
                array_push ($_SESSION['filter_data_type'], $ftype);
            }
        }
    }
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pl" xml:lang="pl">
	<head>
		<title>Filmy</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<style type="text/css">
		    body {
		        font-family: Tahoma, Helvetica, Arial, sans-serif;
		        font-size: 12px;
		    }
            label {
                display: block;
                margin-bottom: 4px;
            }
            label input,
            label textarea,
            label select {
                width: 300px;
            }
            label span {
                color: red;
                font-size: 16px;
                font-weight: bold;
            }
            #film_list {
                margin-bottom: 30px;
                border: 1px solid black;
            }
            #film_list thead th {
                background-color: #eee;
            }
            #film_list tbody td {
                padding: 1px 4px;
            }
            #film_list tbody tr:hover {
                background-color: white;
            }
            .row_dark {
                background-color: #bbb;
            }
            .row_bright {
                background-color: #ddd;
            }
            a {
                color: blue;
                text-decoration: none;
            }
            a:hover {
                color: red;
                text-decoration: underline;
            }

            div.menu {
                display:inline;
                float:left;
            }

		</style>

		<script type="text/javascript">
			function tr_alter (what) {
				if (what.nextSibling.nextSibling.nodeName == 'TR') {
					what.nextSibling.nextSibling.style.display == 'none' ?	what.nextSibling.nextSibling.style.display = 'table-row' :
																			what.nextSibling.nextSibling.style.display = 'none';
				}
			}
		</script>
		<meta name="robots" content="noindex,nofollow" />
		<meta http-equiv="Content-Language" content="pl" />
		<meta name="Generator" content="VIM" />

		<link rel="shortcut icon" href="./icon.ico">
	</head>
	<body>
		<div id="main">
		    <table id="film_list" width="100%">
		        <thead>
		            <tr>
		                <th width="40px"><a href="/?sort=id&order=<?php echo $order_rev; ?>">ID</a></th>
		                <th width=""><a href="/?sort=name&order=<?php echo $order_rev; ?>">Nazwa pliku</a></th>
		                <th width=""><a href="/?sort=title&order=<?php echo $order_rev; ?>">Tytuł</a></th>
		                <th width="40px"><a href="/?sort=imdb_rate&order=<?php echo $order_rev; ?>">IMDB</a></th>
		                <th width="50px"><a href="/?sort=type&order=<?php echo $order_rev; ?>">Typ</a></th>
                        <th width="50px"><a href="/?sort=person&order=<?php echo $order_rev; ?>">Dodał</a></th>
		                <th width="100px"><a href="/?sort=status&order=<?php echo $order_rev; ?>">Status</a></th>
		                <th width="140px"><a href="/?sort=dateadd&order=<?php echo $order_rev; ?>">Data dodania</a></th>
		                <th width="60px">Usuń</th>
		            </tr>
		        </thead>
		        <tbody>
<?php

try {
    if (isset($_SESSION['filter_data_type']) && count ($_SESSION['filter_data_type']) && !isset ($_SESSION['filter_data_type']['none'])) {
        $query = $GLOBALS['sql']->prepare (sprintf ('
            SELECT
                *
            FROM
                `films`
            WHERE
                `data_type` IN (\'%s\')
            ORDER BY `%s` %s',
            implode ("', '", $_SESSION['filter_data_type']),
            $sort,
            $order
        ));
    }

    else {
        $query = $GLOBALS['sql']->prepare (sprintf ('
            SELECT
                *
            FROM
                `films`
            ORDER BY `%s` %s',
            $sort,
            $order
        ));
    }

    $query->execute ();

    for ($lp=1; $row = $query->fetch (PDO::FETCH_LAZY); ++$lp) {
        if (isset ($_GET['flm_id']) && ($_GET['flm_id'] == $row->flm_id)) {
            $film_data['id']        = $row->flm_id;
            $film_data['name']      = $row->flm_name;
            $film_data['title']     = $row->flm_title;
            $film_data['imdb_rate'] = $row->flm_imdb_rate;
            $film_data['notes']     = $row->flm_notes;
            $film_data['status']    = $row->flm_status;
            $film_data['type']      = $row->data_type;
        }

        printf ('
                        <tr class="%s" onclick="tr_alter(this);">
                            <td align="right">%d.</td>
                            <td title="%s"><a href="?action=edit&flm_id=%d">%s</a></td>
                            <td>%s</td>
                            <td align="right">%s</td>
                            <td align="right">%s</td>
                            <td align="right">%s</td>
                            <td align="center"><a href="?action=cycle_status&amp;flm_id=%d" title="%s">%s</a></td>
                            <td align="center">%s</td>
                            <td align="center"><a href="/?action=delete&amp;flm_id=%d"
                                onclick="return confirm (\'Czy aby na pewno usunąć?\')">Usuń</a></th>
                        </tr>
						<tr class="%s" style="display:none;">
							<td colspan="9">
								uwagi: %s
							</td>
						</tr>',

            $lp % 2 ? 'row_dark' : 'row_bright',
			$row->flm_id,
            str_entit ($row->flm_notes),
            $row->flm_id,
            $row->flm_name,
            $row->flm_title,
            $row->flm_imdb_rate,
            $data_types[$row->data_type],
            $row->flm_person,
//            $data_types[$row->data_type],
            $row->flm_id,
            $row->flm_status_person,
            $film_statuses[$row->flm_status],
            $row->flm_dateadd,
            $row->flm_id,
			$lp % 2 ? 'row_dark' : 'row_bright',
			$row->flm_notes
        );
    }
} catch (PDOException $e) {
    debug_array ($e);
}

?>
		        </tbody>
		    </table>

<?php

if (count ($errors)) {
    echo "<p class=\"error\"><h1>Błędy:</h1><ol>\n";
    foreach ($errors as $error) {
        printf ("<li>%s</li>\n", $error);
    }
    echo '</ol></p>';
}

?>
            Menu: <a href="/">[ Nowa pozycja ]</a> | <br /><br />
        </div>
        <div id="add" class="menu">
            <form method="post" action="?action=add">
                <label for="film_name"><input
                    type="text" id="film_name" name="film_name"
                    value="<?php echo str_entit ($film_data['name']); ?>" /> <span>*</span> - nazwa pliku</label>
                <label for="film_title"><input
                    type="text" id="film_title" name="film_title"
                    value="<?php echo str_entit ($film_data['title']); ?>" /> - tytuł filmu</label>
                <label for="film_imdb_rate"><input
                    type="text" id="film_imdb_rate" name="film_imdb_rate"
                    value="<?php echo str_entit ($film_data['imdb_rate']); ?>" /> - ocena w IMDB</label>
                <label for="film_notes"><textarea id="film_notes"
                    name="film_notes"><?php echo str_entit ($film_data['notes']); ?></textarea> - dodatkowe uwagi</label>
                <label for="film_type"><select name="film_type">
<?php
    foreach ($data_types as $val => $name) {
        printf ("<option %s value=\"%s\">%s</option>\n", isset ($film_data['type']) && $val == $film_data['type'] ? 'selected="selected"' : '', $val, $name);
    }
?>
                </select> - typ</label>
                <label for="film_status"><select name="film_status">
<?php
    foreach ($film_statuses as $val => $name) {
        printf ("<option %s value=\"%s\">%s</option>\n", $val == $film_data['status'] ? 'selected="selected"' : '', $val, $name);
    }
?>
                </select> - status</label>
				<?PHP
                    if (isset ($_GET['action']) && $_GET['action'] == 'edit') {
                        echo '<input type="hidden" id="flm_id" name="flm_id" value="'.str_entit ($film_data['id']).'" />';
                    }
                ?>
                <input type="submit" name="film_submit" value="Zapisz" />
            </form>
		</div>
        <div id="filter" align="center">
            <form method="post" action="?action=filter">
                Filtruj po typie: <br /><select name="filter_data_type[]" multiple="true">
<?php
    printf ("<option %s value=\"none\">&lt;wszystkie&gt;</option>\n", isset ($_SESSION['filter_data_type']['none']) ? 'selected="selected"' : '');

    foreach ($data_types as $val => $name) {
        printf ("<option %s value=\"%s\">%s</option>\n", in_array($val, $_SESSION['filter_data_type']) ? 'selected="selected"' : '', $val, $name);
    }
?>
                </select>
                <br />
                <input type="submit" name="filter_submit" value="Filtruj" />
            </form>
        </div>
	</body>
</html>
