<?

if(empty($GLOBALS['SysValue']))
    exit(header("Location: /"));

require_once 'functions.php';

// ����������� ��������� ������� �� $_GET['payment']
if(isset($_GET['order_id']) && isset($_GET['payment']) && $_GET['payment'] == 'passimpay') {

    $order_metod = "passimpay";
    $success_function = false; // ��������� ������� ���������� ������� ������, �������� ��� ��������� � result.php
    $my_crc = "NoN";
    $crc = "NoN";
    $inv_id = $_GET['order_id'];

}