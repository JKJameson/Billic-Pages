<?php
class Pages {
	public $settings = array(
		'name' => 'Pages',
		'admin_menu_category' => 'Settings',
		'admin_menu_name' => 'Pages',
		'admin_menu_icon' => '<i class="icon-sitemap"></i>',
		'description' => 'Add pages to Billic to act like website pages. Supports PHP code embedded inside the pages.',
	);
	function checkPHP($code) {
		global $billic, $db;
		ob_start();
		$eval = @eval('?>' . $code);
		ob_get_clean();
		if ($eval === FALSE) {
			return false;
		} else {
			return true;
		}
	}
	function admin_area() {
		global $billic, $db;
		if (isset($_GET['URI'])) {
			if (isset($_POST['update'])) {
				if (in_array(strtolower($_POST['uri']) , $GLOBALS['billic_reserved_pages'])) {
					$billic->errors[] = 'The URI "' . $_POST['uri'] . '" is reserved';
				}
				if (empty($billic->errors)) {
					$pageid = $db->q('SELECT `id` FROM `pages` WHERE `uri` = ?', urldecode($_GET['URI']));
					$pageid = $pageid[0]['id'];
					$check = $db->q('SELECT COUNT(*) FROM `pages` WHERE `uri` = ? AND `id` != ?', $_POST['uri'], $pageid);
					if ($check[0]['COUNT(*)'] > 0) {
						$billic->errors[] = 'The URI is already in use';
					}
				}
				if (!$this->checkPHP($_POST['content'])) {
					$billic->errors[] = 'There is a PHP Error with the content.';
				}
				if (empty($billic->errors)) {
					$db->q('UPDATE `pages` SET `uri` = ?, `menu_show` = ?, `menu_name` = ?, `menu_icon` = ? WHERE `uri` = ?', $_POST['uri'], $_POST['menu_show'], $_POST['menu_name'], $_POST['menu_icon'], urldecode($_GET['URI']));
					if ($_POST['uri'] != $_GET['URI']) {
						// URI was changed
						$billic->redirect('/Admin/Pages/URI/' . urlencode($_POST['uri']) . '/');
					}
					$billic->status = 'updated';
				}
			}
			if (isset($_GET['AjaxSave'])) {
				$billic->disable_content();
				if (!$this->checkPHP($_POST['page_data'])) {
					echo 'A PHP error was detected inside the page.';
					exit;
				}
				$db->q('UPDATE `pages` SET `content` = ? WHERE `URI` = ?', $_POST['page_data'], urldecode($_GET['URI']));
				echo 'OK';
				exit;
			}
			$page = $db->q('SELECT * FROM `pages` WHERE `uri` = ?', urldecode($_GET['URI']));
			$page = $page[0];
			if (empty($page)) {
				err('Page does not exist');
			}
			if (!empty($billic->errors)) {
				$page['uri'] = $_POST['uri'];
				$page['menu_show'] = $_POST['menu_show'];
				$page['menu_name'] = $_POST['menu_name'];
				$page['menu_icon'] = $_POST['menu_icon'];
				$page['pagecontent'] = $_POST['pagecontent'];
			}
			$billic->set_title('Admin/Page ' . safe($page['uri']));
			echo '<h1>Page ' . safe($page['uri']) . '</h1>';
			$billic->show_errors();
			echo '<form method="POST"><table class="table table-striped"><tr><th colspan="2">Page Settings</th></td></tr>';
			echo '<tr><td width="125">URI</td><td><input type="text" class="form-control" name="uri" value="' . safe($page['uri']) . '"></td></tr>';
			echo '<tr><th colspan="2">Menu Settings</th></td></tr>';
			echo '<tr><td width="125">Show on Menu</td><td><input type="checkbox" name="menu_show" value="1"' . ($page['menu_show'] == 1 ? ' checked' : '') . '></td></tr>';
			echo '<tr><td width="125">Menu Name</td><td><input type="text" class="form-control" name="menu_name" value="' . safe($page['menu_name']) . '"></td></tr>';
			echo '<tr><td width="125">Menu Icon</td><td><input type="text" class="form-control" name="menu_icon" value="' . safe($page['menu_icon']) . '"></td></tr>';
			echo '<tr><td colspan="4" align="center"><input type="submit" class="btn btn-success" name="update" value="Update Settings &raquo;"></td></tr>';
			echo '</table></form>';
			echo '<button class="btn btn-default disabled" style="position:fixed;right:100px;z-index: 5000;" id="savePageBtn" onClick="savePage()"><i class=\"icon-save-disk\"></i> No Changes</button><br><br>';
			echo '<textarea id="pagecontent" style="width: 100%; height:500px">' . safe($page['content']) . '</textarea>';
			echo '
<link rel="stylesheet" href="/Modules/Core/codemirror/codemirror.css">
<script src="/Modules/Core/codemirror/codemirror.js"></script>
<script src="/Modules/Core/codemirror/matchbrackets.js"></script>
<script src="/Modules/Core/codemirror/htmlmixed.js"></script>
<script src="/Modules/Core/codemirror/xml.js"></script>
<script src="/Modules/Core/codemirror/javascript.js"></script>
<script src="/Modules/Core/codemirror/css.js"></script>
<script src="/Modules/Core/codemirror/clike.js"></script>
<script src="/Modules/Core/codemirror/php.js"></script>
			
<script>
var pageChanged = false;
var editor = CodeMirror.fromTextArea(document.getElementById("pagecontent"), {
	lineNumbers: true,
	lineWrapping: true,
	mode: "application/x-httpd-php",
	matchBrackets: true,
	indentUnit: 4,
	indentWithTabs: true
});
editor.on("change", function() {
	if (pageChanged==false) {
		enableSaveBtn();
		pageChanged = true;
	}
});
function enableSaveBtn() {
	$("#savePageBtn").removeClass( "btn-default disabled" ).addClass( "btn-success" );	
	$("#savePageBtn").html("<i class=\"icon-save-disk\"></i> Save Changes &raquo;");
}
function savePage() {
	$.post( "/Admin/Pages/URI/' . urlencode($page['uri']) . '/AjaxSave/", { page_data: editor.getValue() })
		.done(function( data ) {
			if (data==\'OK\') {
				pageChanged = false;
				$("#savePageBtn").removeClass( "btn-success" ).addClass( "btn-default btn-disabled" );	
				$("#savePageBtn").html("<i class=\"icon-save-disk\"></i> Saved!");
			} else {
				alert("Error saving: "+data);
			}
		});
}
</script>';
			return;
		}
		if (isset($_GET['New'])) {
			$title = 'New Page';
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			$billic->module('FormBuilder');
			$form = array(
				'uri' => array(
					'label' => 'URI',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				if (in_array(strtolower($_POST['uri']) , $GLOBALS['billic_reserved_pages'])) {
					$billic->errors[] = 'The URI "' . $_POST['uri'] . '" is reserved';
				}
				if (empty($billic->errors)) {
					$check = $db->q('SELECT COUNT(*) FROM `pages` WHERE `uri` = ?', $_POST['uri']);
					if ($check[0]['COUNT(*)'] > 0) {
						$billic->errors[] = 'The URI is already in use';
					}
				}
				if (empty($billic->errors)) {
					$db->insert('pages', array(
						'uri' => $_POST['uri'],
					));
					$billic->redirect('/Admin/Pages/URI/' . urlencode($_POST['uri']) . '/');
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		if (isset($_GET['Delete'])) {
			$db->q('DELETE FROM `pages` WHERE `uri` = ?', urldecode($_GET['Delete']));
			$billic->status = 'deleted';
		}
		if (isset($_GET['Move']) && isset($_GET['Action'])) {
			$page_to_move = urldecode($_GET['Move']);
			$page_to_move_weight = $db->q('SELECT `weight` FROM `pages` WHERE `uri` = ?', $page_to_move);
			$page_to_move_weight = $page_to_move_weight[0]['weight'];
			if ($_GET['Action'] == 'UpTop') {
				$db->q('UPDATE `pages` SET `weight` = (`weight`+1) WHERE `uri` != ?', $page_to_move);
				$db->q('UPDATE `pages` SET `weight` = `weight` = 0 WHERE `uri` = ?', $page_to_move);
			} else if ($_GET['Action'] == 'Up') {
				$db->q('UPDATE `pages` SET `weight` = (`weight`+1) WHERE `weight` < ? ORDER BY `weight` DESC LIMIT 1', $page_to_move_weight);
				$db->q('UPDATE `pages` SET `weight` = (`weight`-1) WHERE `uri` = ?', $page_to_move);
			} else if ($_GET['Action'] == 'Down') {
				$db->q('UPDATE `pages` SET `weight` = (`weight`-1) WHERE `weight` > ? ORDER BY `weight` ASC LIMIT 1', $page_to_move_weight);
				$db->q('UPDATE `pages` SET `weight` = (`weight`+1) WHERE `uri` = ?', $page_to_move);
			} else if ($_GET['Action'] == 'DownBottom') {
				$db->q('UPDATE `pages` SET `weight` = (`weight`-1) WHERE `weight` >= 99999');
				$db->q('UPDATE `pages` SET `weight` = 99999 WHERE `uri` = ?', $page_to_move);
			} else {
				err('Invalid Action');
			}
			$i = 0;
			$pages = $db->q('SELECT `id` FROM `pages` ORDER BY `weight`, `menu_name`');
			foreach ($pages as $page) {
				$db->q('UPDATE `pages` SET `weight` = ? WHERE `id` = ?', $i, $page['id']);
				$i++;
			}
			$billic->status = 'updated';
		}
		$billic->set_title('Admin/Pages');
		echo '<h1><i class="icon-sitemap"></i> Pages</h1>';
		$billic->show_errors();
		echo '<a href="New/" class="btn btn-success"><i class="icon-plus"></i> New Page</a>';
		$pages = $db->q('SELECT `uri`, `menu_icon`, `menu_name`, LENGTH(`content`), `menu_show` FROM `pages` ORDER BY `weight`, `menu_name`');
		echo '<table class="table table-striped"><tr><th>Menu Name</th><th>URI</th><th>Size</th><th>Show on Menu</th><th>Actions</th></tr>';
		if (empty($pages)) {
			echo '<tr><td colspan="20">No Pages matching filter.</td></tr>';
		}
		foreach ($pages as $page) {
			if (empty($page['menu_name'])) {
				$page['menu_name'] = '-';
			}
			echo '<tr><td>' . $page['menu_icon'] . '&nbsp;' . safe($page['menu_name']) . '</td><td><i class="icon-link"></i>&nbsp;<a href="/' . urlencode($page['uri']) . '">' . safe($page['uri']) . '</a></td><td>' . safe(number_format($page['LENGTH(`content`)'] / 1024, 2) . ' KB') . '</td><td>' . ($page['menu_show'] == 1 ? '<i class="icon-check-mark"></i>' : '<i class="icon-remove"></i>') . '</td><td>';
			echo '<a href="/Admin/Pages/URI/' . urlencode($page['uri']) . '/" class="btn btn-primary btn-xs"><i class="icon-edit-write"></i> Edit</a>';
			echo '&nbsp;<a href="/Admin/Pages/Delete/' . urlencode($page['uri']) . '/" class="btn btn-danger btn-xs" title="Delete" onClick="return confirm(\'Are you sure you want to delete?\');"><i class="icon-remove"></i> Delete</a>';
			echo '&nbsp;<a href="/Admin/Pages/Move/' . urlencode($page['uri']) . '/Action/DownBottom/" class="btn btn-info btn-xs" title="Move To Bottom"><i class="icon-chevron-down-circle"></i></a>';
			echo '&nbsp;<a href="/Admin/Pages/Move/' . urlencode($page['uri']) . '/Action/Down/" class="btn btn-info btn-xs" title="Move Down"><i class="icon-chevron-down"></i></a>';
			echo '&nbsp;<a href="/Admin/Pages/Move/' . urlencode($page['uri']) . '/Action/Up/" class="btn btn-info btn-xs" title="Move Up"><i class="icon-chevron-up"></i></a>';
			echo '&nbsp;<a href="/Admin/Pages/Move/' . urlencode($page['uri']) . '/Action/UpTop/" class="btn btn-info btn-xs" title="Move to Top"><i class="icon-chevron-up-circle"></i></a>';
			echo '</td></tr>';
		}
		echo '</table>';
	}
}
