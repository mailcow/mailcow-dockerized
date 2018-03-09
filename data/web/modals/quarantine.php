<?php
if (!isset($_SESSION['mailcow_cc_role'])) {
	header('Location: /');
	exit();
}
?>
<div class="modal fade" id="qidDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><span class="glyphicon glyphicon-info"></span> <?=$lang['quarantine']['qitem'];?></h3>
      </div>
      <div class="modal-body">
        <div id="qid_error" style="display:none" class="alert alert-danger"></div>
        <div class="form-group">
          <label for="qid_detail_subj"><h4><?=$lang['quarantine']['subj'];?>:</h4></label>
          <p id="qid_detail_subj"></p>
        </div>
        <div class="form-group">
          <label for="qid_detail_text"><h4><?=$lang['quarantine']['text_plain_content'];?>:</h4></label>
          <pre id="qid_detail_text"></pre>
        </div>
        <div class="form-group">
          <label for="qid_detail_text_from_html"><h4><?=$lang['quarantine']['text_from_html_content'];?>:</h4></label>
          <pre id="qid_detail_text_from_html"></pre>
        </div>
        <div class="form-group">
          <label for="qid_detail_atts"><h4><?=$lang['quarantine']['atts'];?>:</h4></label>
          <div id="qid_detail_atts">-</div>
        </div>
      </div>
    </div>
  </div>
</div>

