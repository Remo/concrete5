<?
defined('C5_EXECUTE') or die("Access Denied.");
$form = Loader::helper('form');?>

<? if (is_array($selectedGroups)) { ?>

<h4><?=t('Confirm')?></h4>
<? if ($gParent instanceof Group) { ?>
<p><?=t('Move the following group(s) beneath <strong>%s</strong>.', $gParent->getGroupDisplayName())?></p>
<? } else { ?> 
<p><?=t('Move the following group(s) <strong>to the top level of groups</strong>.')?></p>
<? } ?>

<ul>
<? foreach($selectedGroups as $g) { ?>
	<li><?=$g->getGroupDisplayName()?></li>
<? } ?>
</ul>

<form method="post" action="<?=$view->action('confirm')?>" role="form">
    <input type="hidden" name="gParentNodeID" value="<?=h($_REQUEST['gParentNodeID'])?>" />
    
	<? foreach($_REQUEST['gID'] as $gID) { ?>
		<input type="hidden" name="gID[]" value="<?=h($gID)?>" />
	<? } ?>
	<br/>
	<input type="hidden" name="gName" value="<?=h($_REQUEST['gName'])?>" />
	
	<div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <?php echo $interface->submit(t('Move Groups'), '', 'right', 'btn-primary'); ?>
        </div>
    </div>
</form>

<? } else if (is_array($groups)) { ?>

<form action="<?=$view->action('move')?>" method="post" data-form="move-groups">

    <div class="row">
        <div class="col-md-6">
            <h4><?=t('Choose Groups to Move')?></h4>

            <div class="checkbox">
                <label style="user-select: none; -moz-user-select: none; -webkit-user-select: none">
                    <input data-toggle="checkbox" type="checkbox" /> <strong><?=t('Select All')?></strong>
                </label>
            </div>
            <? foreach($groups as $g) { ?>
                <div class="checkbox" data-checkbox="group-list"><label>
                    <input name="gID[]" type="checkbox" <? if (is_array($_POST['gID']) && in_array($g->getGroupID(), $_POST['gID'])) { ?>checked<? } ?> value="<?=$g->getGroupID()?>" /> <?=$g->getGroupDisplayName()?>
                </label></div>
            <? } ?>
        </div>
        
        <div class="col-md-6">
            <h4><?=t('Choose New Parent Location')?></h4>
            
            <?=$form->hidden('gParentNodeID')?>
            
            <div class="nested-groups-tree" data-groups-tree="<?=$tree->getTreeID()?>">
            
            </div>
            
            <?
            $guestGroupNode = GroupTreeNode::getTreeNodeByGroupID(GUEST_GROUP_ID);
            $registeredGroupNode = GroupTreeNode::getTreeNodeByGroupID(REGISTERED_GROUP_ID);
            ?>
        </div>
    </div>
    
    <hr/>
    
    <div class="row">
        
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <h3><?=t('Move')?></h3>
            <p><?=t('Move selected groups (left column) beneath selected group (right column)')?></p>
            
            <div class="ccm-dashboard-form-actions-wrapper">
                <div class="ccm-dashboard-form-actions">
                    <?php echo $interface->submit(t('Move'), '', 'right', 'btn-primary'); ?>
                </div>
            </div>
        </div>
    </div>

	<input type="hidden" name="gName" value="<?=h($_REQUEST['gName'])?>" />
</form>

<script type="text/javascript">
$(function() {
	$('input[data-toggle=checkbox]').on('click', function() {
		if ($(this).is(':checked')) {
			$('div[data-checkbox=group-list] input[type=checkbox]').prop('checked', true);
		} else {
			$('div[data-checkbox=group-list]  input[type=checkbox]').prop('checked', false);

		}
	});
})
</script>

<script type="text/javascript">
    $(function() {
       $('[data-groups-tree=<?=$tree->getTreeID()?>]').concreteGroupsTree({
          'treeID': '<?=$tree->getTreeID()?>',
          'chooseNodeInForm': 'single',
		  'enableDragAndDrop': false,
          <? if ($this->controller->isPost()) { ?>
             'selectNodesByKey': [<?=intval($_POST['gParentNodeID'])?>],
          <? } ?>
          'removeNodesByID': ['<?=$guestGroupNode->getTreeNodeID()?>','<?=$registeredGroupNode->getTreeNodeID()?>'],
          'onSelect': function(select, node) {
             if (select) {
                $('input[name=gParentNodeID]').val(node.data.key);
             } else {
                $('input[name=gParentNodeID]').val('');
             }
          }
       });
    });
    </script>

<? } else { ?>

<form method="POST" action="<?=$view->action('search')?>">
	<h4><?=t('Search for Groups to Move')?></h4>
	
	<div class="row">
	    <div class="col-md-6">
	        <fieldset>
            	<div class="form-group">
                    <?=$form->text('gName')?>
            	</div>
            </fieldset>
            
        	<div class="ccm-dashboard-form-actions-wrapper">
                <div class="ccm-dashboard-form-actions">
                    <?php echo $interface->submit(t('Search'), '', 'right', 'btn-primary'); ?>
                </div>
            </div>
	    </div>
	</div>
</form>

<? } ?>

<?=Loader::helper('concrete/dashboard')->getDashboardPaneFooterWrapper(false);?>
