<?php
/**
 * ��������� ����� ����� CFile � webdav_client, ��������� ���������� ��������� ��������.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . "/classes/stdf.php");
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/webdav_client.php');
require_once($_SERVER['DOCUMENT_ROOT'] . "/classes/log.php");

class webdav_proxy {
    
    /**
     * ������� ������.
     * @see webdav_proxy::getInst()
     * @var webdav_proxy
     */
    static private $_inst;
    
    /**
     * ������� webdav_client �� ������ ������ WebDAV.
     * @var array
     */
    private $_wdcs = array();
    
    /**
     * ������
     * @var log
     */
    private $_log;
    
    /**
     * ����������� (��������� -- ����� �� ���� ������ �����������). ��������� ������������ ��������.
     * @param array $wdcs_prms   ������������ �������� WebDAV: array (array ('server'=>'dav.free-lance.ru','prefix'=>'http://dav.free-lance.ru', 'port'=>80, 'user'=>'DAV', 'pass'=>'test', 'debug'=>true), array(...), ... );
     */
    private function __construct($wdcs_prms) {
        $this->_log = new log('webdav/'.SERVER.'-%d%m%Y.log', 'a', '%d.%m.%Y %H:%M:%S : ');
        $this->setWdcParams($wdcs_prms);
    }
    
    /**
     * ����� ��� ��������� �������� ����������.
     *
     * @param array $wdcs_prms   ������������ �������� WebDAV (��. �����������)
     * @return webdav_proxy
     */
    static function getInst($wdcs_prms = NULL) {
        if(!self::$_inst) {
            self::$_inst = new webdav_proxy($wdcs_prms);
        }
        return self::$_inst;
    }
    
    /**
     * ��������� ������������ ��������.
     * @param array $wdcs_prms   ������������ �������� WebDAV (��. �����������)
     */
    function setWdcParams($wdcs_prms) {
        foreach ($wdcs_prms as $wdid=>$prms) {
            if (($owdc = $this->_wdcs[$wdid]) && $owdc->connected) {
                $owdc->close();
            }
            $this->_wdcs[$wdid] = $wdc = new webdav_client();
            $wdc->set_server($prms['server']);
            $wdc->set_port($prms['port']);
            $wdc->set_user($prms['user']);
            $wdc->set_pass($prms['pass']);
            $wdc->set_protocol((int)$prms['protocol']); // use HTTP/1.0 - 1.1 �������� � ������� � ��������� � ���������
            $wdc->set_debug($prms['debug']);
            $wdc->prefix = $prms['prefix'];
            $wdc->prefix_local = 'http://' . $prms['server'] .':'. $prms['port']; // ��� ���� ������ "http".
            $wdc->is_reserved = $prms['is_reserved'];
        }
    }
    
    /**
     * ���������� ������� � �������.
     *
     * @param webdav_client $wdc   ������ �������
     * @return boolean   �������?
     */
    private function _connect($wdc) {
        if(!$wdc->connected) {
            if(!($wdc->connected = $wdc->open())) {
                $this->_log->writeln("error {$rc}: could not open server connection: {$wdc->_server}");
            }
        }
        return $wdc->connected;
    }
    
    /**
     * ��������� ����.
     * @see webdav_client::put_file()
     * @param boolean $exclude_reserved_wdc   ��������� �� ��������� ������� (��� �����-�� ��������� ������, ���� �� ��������� ������ ���).
     * @return boolean   �������?
     */
    function put_file($path, $filename, $exclude_reserved_wdc = false) {
        if(!$this->safePath($path.$filename)) {
            return false;
        }
        foreach($this->_wdcs as $wdc) {
            if($exclude_reserved_wdc && $wdc->is_reserved) {
                continue;
            }
            if($ok = $this->_connect($wdc)) {
                $rc = $wdc->put_file($path, $filename);
                $ok = ($rc == 201 || $rc == 204);
            }
            if(!$ok) { // ���� ���� �� ��, �������.
                $this->_log->writeln("error {$rc}: could not put file: {$filename}, to: {$path}, server: {$wdc->_server}");
                return false;
            }
        }
        return true;
    }
    
    /**
     * ������� ����� ���� � ������� ���������.
     * @see webdav_client::put()
     * @param boolean $exclude_reserved_wdc   ��������� �� ��������� ������� (��� �����-�� ��������� ������, ���� �� ��������� ������ ���).
     * @return boolean   �������?
     */
    function put($path, $content, $exclude_reserved_wdc = false) {
        if(!$this->safePath($path)) {
            return false;
        }
        foreach($this->_wdcs as $wdc) {
            if($exclude_reserved_wdc && $wdc->is_reserved) {
                continue;
            }
            if($ok = $this->_connect($wdc)) {
                $rc = $wdc->put($path, $content);
                $ok = ($rc == 201 || $rc == 204);
            }
            if(!$ok) { // ���� ���� �� ��, �������.
                $this->_log->writeln("error {$rc}: could not put content path: {$path}, server: {$wdc->_server}");
                return false;
            }
        }
        return true;
    }
    
    /**
     * ������� ����/����������.
     * @see webdav_client::delete()
     * @param boolean $exclude_reserved_wdc   ��������� �� ��������� ������� (��� �����-�� ��������� ������, ���� �� ��������� ������ ���).
     * @return boolean   �������?
     */
    function delete($path, $exclude_reserved_wdc = false) {
        if(!$this->safePath($path)) {
            return false;
        }
        $ret = false;
        foreach($this->_wdcs as $wdc) {
            if($exclude_reserved_wdc && $wdc->is_reserved) {
                continue;
            }
            $ok = $this->_connect($wdc) && $wdc->delete($path);
            if(!$ok) {
                $this->_log->writeln("error {$rc}: could not delete: {$path}, server: {$wdc->_server}");
            }
            $ret = $ret || $ok; // ����� ���� �� ���� ��, ����� �������, ��� ����� ���.
        }
        return $ret;
    }
    
    /**
     * �������� ����/����������.
     * @see webdav_client::copy_file()
     * @return boolean   �������?
     */
    function copy_file($src_path, $dst_path, $overwrite) {
        if(!$this->safePath($src_path) || !$this->safePath($dst_path)) {
            return false;
        }
        foreach($this->_wdcs as $wdc) {
            if($ok = $this->_connect($wdc)) {
                $rc = $wdc->copy_file($src_path, $dst_path, $overwrite);
                $ok = ($rc == 201 || $rc == 204);
            }
            if(!$ok) { // ���� ���� �� ��, �������.
                $this->_log->writeln("error {$rc}: could not copy file: {$src_path}, to: {$dst_path}, server: {$wdc->_server}");
                return false;
            }
        }
        return true;
    }
    
    /**
     * ����������/��������������� ����/����������.
     * @see webdav_client::move()
     * @return boolean   �������?
     */
    function move($src_path, $dst_path, $overwrite) {
        if(!$this->safePath($src_path) || !$this->safePath($dst_path)) {
            return false;
        }
        $moved = array();
        foreach($this->_wdcs as $wdc) {
            if($ok = $this->_connect($wdc)) {
                $rc = $wdc->move($src_path, $dst_path, $overwrite);
                $ok = ($rc == 201 || $rc == 204);
            }
            if(!$ok) {
                $this->_log->writeln("error {$rc}: could not move file: {$src_path}, to: {$dst_path}, server: {$wdc->_server}");
                foreach($moved as $wdc) {
                    $rc = $wdc->move($dst_path, $src_path, true);
                    $ok = ($rc == 201 || $rc == 204);
                    if(!$ok) {
                        $this->_log->writeln("error {$rc}: could not unmove file: {$dst_path}, to: {$src_path}, server: {$wdc->_server}");
                    }
                }
                return false;
            }
            $moved[] = $wdc;
        }
    
        return true;
    }
    
    /**
     * ������� ����������.
     * @see webdav_client::mkcol()
     * @return boolean   �������?
     */
    function mkcol($path) {
        if(!$this->safePath($path)) {
            return false;
        }
        foreach($this->_wdcs as $wdc) {
            if($ok = $this->_connect($wdc)) {
                $rc = $wdc->mkcol($path);
                $ok = ($rc == 201 || $rc == 405);
            }
            if(!$ok) { // ���� ���� �� ��, �������.
                $this->_log->writeln("error {$rc}: could not make col: {$path}, server: {$wdc->_server}");
                return false;
            }
        }
        return true;
    }
    
    /**
     * ���������, ���������� �� ����/����������.
     * @param string $path   ���� �� ����� (��� ������� �����).
     * @param boolean $dont_check_put    true: �� ���������, ���� webdav ����� �� nginx � ������� create_full_put_path, ������ ������� true.
     * @return boolean   true:���������� �� ���� ��������, false:�� ���������� ���� �� �� ����� ��� ������.
     */
    function check_file($path, $dont_check_put = false) {
        if( !($dont_check_put && defined('WD_CREATE_FULL_PUT_PATH') && WD_CREATE_FULL_PUT_PATH) ) {
            foreach($this->_wdcs as $id=>$wdc) {
                $info = $this->get_info($id, $path);
                if(!$info || $info['http_code'] == 404) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * ���������� mime-��� �����.
     * @param string $path   ���� �� ����� (��� ������� �����).
     * @return string   ���, ��������, "image/png".
     */
    function get_content_type($path) {
        foreach($this->_wdcs as $id=>$wdc) {
            $info = $this->get_info($id, $path);
            if($info && $info['http_code'] != 404) {
                return $info['content_type']; // c ������� �������� �������� � �������.
            }
        }
        return NULL;
    }
    
    /**
     * ���������� ���������� � �����.
     * @param integer $wdid  ��. DAV-����������.
     * @param string $path   ���� �� ����� (��� ������� �����).
     * @return array
     */
    function get_info($wdid, $path) {
        $info = NULL;
        if($wdc = $this->_wdcs[$wdid]) {
            $curl = curl_init(); 
            curl_setopt($curl, CURLOPT_URL, $wdc->prefix_local.'/'.$path);
            curl_setopt($curl, CURLOPT_FILETIME, true); 
            curl_setopt($curl, CURLOPT_TIMEOUT, 20); 
            curl_setopt($curl, CURLOPT_NOBODY, true); 
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
            if(defined('BASIC_AUTH')) {
                curl_setopt($curl, CURLOPT_USERPWD, BASIC_AUTH);
            }
            if(curl_exec($curl) !== false) {
                $info = curl_getinfo($curl);
                if($info['total_time'] > 1) {
                    $this->_log->writeln("warning: getinfo() takes too much time: {$info['total_time']}; url: {$info['url']}, server: {$wdc->_server}");
                }
            } else {
                $this->_log->writeln('error: getinfo() curl_error: ' . curl_error($curl) . "; url: {$info['url']}, server: {$wdc->_server}");
            }
            curl_close($curl);
        }
        return $info;
    }
    
    
    /**
     * �������, ���������� �� ���� (��� copy, delete � �.�.)
     * @param string $path   ���� �� �����/�����
     * @return boolean
     */
    function safePath($path) {
        return ( $path && strpos($path, '../') === false && preg_match('/[a-zA-Z]/', $path) );
    }

    
    /**
     * �������� ���� �� WebDav � ��������� �������� �������
     * 
     * @param type $srcpath
     * @param type $localpath
     */
    function get_file($src_path, $localpath) 
    {
        foreach ($this->_wdcs as $wdc) {
            if ($ok = $this->_connect($wdc)) {
                $ok = $wdc->get_file($src_path, $localpath);
            }

            if (!$ok) {
                $this->_log->writeln("error: could not get file: {$src_path}, to: {$localpath}, server: {$wdc->_server}");
            } else {
                return true;
            }
        }
        
        return false;        
    }
    
    
    /**
     * �������� ������ ������ �� WebDav � ��������� �������� �������
     * ������ ������� ������ array("remotepath" => "localpath")
     * 
     * @param type $filelist
     * @return boolean
     */
    function get_files($filelist)
    {
        foreach ($this->_wdcs as $wdc) {
            if ($ok = $this->_connect($wdc)) {
                $ok = $wdc->mget($filelist);
            }

            if (!$ok) {
                $filelist = implode(', ', array_map(
                    function ($v, $k) { return sprintf("from:%s to:%s", $k, $v); }, 
                    $filelist, 
                    array_keys($filelist)));
                $this->_log->writeln("error: could not get files {$filelist}, server: {$wdc->_server}");
            } else {
                return true;
            }
        }
        
        return false;
    }
    
}
