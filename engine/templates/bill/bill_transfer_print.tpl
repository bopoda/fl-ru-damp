<?
	require_once($_SERVER['DOCUMENT_ROOT'] . "/classes/reqv_ordered.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . "/classes/sbr.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . "/classes/num_to_word.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . "/classes/static_compress.php");
	session_start();
	if (!$_SESSION['login']) {header ("Location: /403.php"); exit;}
	
	$tid = $$tid;
	$reqv = new reqv_ordered();
	if ($tid) $has_reqv = $reqv->GetRow($tid, (hasPermissions('bank') && hasPermissions('adm')) ? '' :  " AND user_id='".get_uid()."'");
	if(!$reqv->id) {header ("Location: /403.php"); exit;}
	$sum = $reqv->ammount;
	if (is_admin()||is_admin_sm()) {
		$acc = new account();
		$acc->GetInfo($reqv->user_id);
		$acc_num = $acc->id;
	} else {
		$acc_num = $$account->id;
	} 
	$billCode = '�-'.$acc_num.'-'.($reqv->bill_no+1);
    if($reqv->sbr_id) {
        $sbr = new sbr_emp($reqv->user_id);
        if($sbr->initFromId($reqv->sbr_id, false, false, NULL, false)) {
            $contract_num = $sbr->getContractNum();
		    $billCode = '�-'.$contract_num;
		    $sbr_nds = $sbr->getCommNds($sbr_comm);
        }
    }
    $ord_num = $reqv->id;
    $sum = round($sum,2);
    if($sbr_nds) {
        $sbr_nds = round($sbr_nds,2);
        $sbr_comm = round($sbr_comm,2);
    }
    $stc = new static_compress;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<HTML><HEAD><TITLE>����</TITLE>
<META http-equiv=Content-Type content="text/html; charset=windows-1251">
<?php $stc->Send(); ?>
<META content="MSHTML 6.00.2900.2963" name=GENERATOR></HEAD>
<BODY><BR><BR>
<TABLE width="90%" border=0 xmlns:str="http://exslt.org/strings" 
xmlns:math="http://exslt.org/math">
  <TBODY>
  <TR>
    <TD><?= PrintSiteLogo(); ?></TD>
    <TD vAlign=top align=right>
      <DIV style="FONT-SIZE: 10pt"><B>129223, ������, �/� 33</B>
	  </DIV></TD></TR></TBODY></TABLE>
<DIV style="FONT-SIZE: 11pt" align=center xmlns:str="http://exslt.org/strings" 
xmlns:math="http://exslt.org/math"><B>������� ���������� ���������� 
���������</B></DIV><BR xmlns:str="http://exslt.org/strings" 
xmlns:math="http://exslt.org/math">
<TABLE class=invoice cellSpacing=0 cellPadding=3 width="90%" border=0 
xmlns:str="http://exslt.org/strings" xmlns:math="http://exslt.org/math">
<TBODY>
              <TR>
                <TD>����������<BR>��� 7805399430 / ��� 771401001 ��� &laquo;����&raquo;</TD>
                <TD align=middle><BR>��. �</TD>
                <TD><BR>40702810787880000803</TD></TR>
              <TR>
                <TD rowSpan=2>���� ����������<BR>� ���������� ������ ��� ��� �������ʻ �. ������
</TD>
                <TD align=middle>���</TD>
                <TD rowSpan=2>044583272<BR>30101810000000000272</TD></TR>
              <TR>
    <TD align=middle>��. �</TD></TR></TBODY></TABLE><BR 
xmlns:str="http://exslt.org/strings" xmlns:math="http://exslt.org/math"><BR 
xmlns:str="http://exslt.org/strings" xmlns:math="http://exslt.org/math">
<DIV style="FONT-SIZE: 12pt" align=center xmlns:str="http://exslt.org/strings" 
xmlns:math="http://exslt.org/math"><B>���� � <?=$billCode?> �� <?=(date("d ",strtotime($reqv->op_date)).strtolower(monthtostr(date("m",strtotime($reqv->op_date)))).date(" Y �.",strtotime($reqv->op_date)))?></B></DIV><BR xmlns:str="http://exslt.org/strings" 
xmlns:math="http://exslt.org/math">
<TABLE width="90%" border=0 xmlns:str="http://exslt.org/strings" 
xmlns:math="http://exslt.org/math">
  <TBODY>
  <TR>
    <TD width="50%">
      <DIV style="FONT-SIZE: 10pt">��������: <?= reformat($reqv->full_name, 28)?></DIV></TD>
                <TD width="50%">
                  <DIV style="FONT-SIZE: 10pt">��������: <?=$reqv->phone?></DIV></TD></TR>
              <TR>
                <TD width="50%">
                  <DIV style="FONT-SIZE: 10pt">������������� ���������: <?=$reqv->fio?>
</DIV></TD>
                <TD width="50%">
                  <DIV style="FONT-SIZE: 10pt">����: <?=$reqv->fax?></DIV></TD></TR></TBODY></TABLE><BR 
xmlns:str="http://exslt.org/strings" xmlns:math="http://exslt.org/math">
<TABLE class=invoice cellSpacing=0 cellPadding=3 width="90%" border=0>
{{include "bill/bill_transfer_form.tpl"}}
