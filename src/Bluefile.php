<?php
namespace Desean1625;
/**
 * Xmidas Bluefile reader Unpacks headers and extended headers
 *
 * @author Sean Sullivan
 * @version 0.9
 */
class Bluefile
{
    /**
     * @var array
     */
    private $bstructs = array(
        "HEADER"    => array(
            "version"    => "4s", "head_rep" => "4s",
            "data_rep"   => "4s", "detached" => "i",
            "protected"  => "i", "pipe"      => "i",
            "ext_start"  => "i", "ext_size"  => "i",
            "data_start" => "d", "data_size" => "d",
            "type"       => "i", "format"    => "2s",
            "flagmask"   => "h", "timecode"  => "d",
            "inlet"      => "h", "outlets"   => "h",
            "outmask"    => "i", "pipeloc"   => "i",
            "pipesize"   => "i", "in_byte"   => "d",
            "out_byte"   => "d", "outbytes"  => "d",
            "keylength"  => "i", "keywords"  => "92s",
        ),
        "ADJUNCTT1" => array(
            "xstart" => "d", "xdelta" => "d",
            "xunits" => "i", "fill1"  => "i",
            "fill2"  => "d", "fill3"  => "d",
            "fill4"  => "i", "bid"    => "i",
        ),
        "ADJUNCTT2" => array(
            "xstart" => "d", "xdelta"  => "d",
            "xunits" => "i", "subsize" => "i",
            "ystart" => "d", "ydelta"  => "d",
            "yunits" => "i", "bid"     => "i",
        ),
        "ADJUNCTT3" => array(
            "rstart"  => "d", "rdelta"        => "d",
            "runits"  => "i", "subrecords"    => "i",
            "r2start" => "d", "r2delta"       => "d",
            "r2units" => "i", "record_length" => "i",
            "subr"    => "208s"),
        "ADJUNCTT4" => array(
            "vrstart"  => "", "vrdelta"        => "",
            "vrunits"  => "", "nrecords"       => "",
            "vr2start" => "", "vr2delta"       => "",
            "vr2units" => "", "vrecord_length" => "",

        ),
        "ADJUNCTT5" => array(
            "tstart"  => "d", "tdelta"        => "d",
            "tunits"  => "i", "components"    => "i",
            "t2start" => "d", "t2delta"       => "d",
            "t2units" => "i", "record_length" => "i",
            "comp"    => "112s", "quadwords"  => "96s",
        ),
        "ADJUNCTT6" => array(
            "rstart"  => "d", "rdelta"        => "d",
            "runits"  => "i", "subrecords"    => "i",
            "r2start" => "d", "r2delta"       => "d",
            "r2units" => "i", "record_length" => "i",
            "subr"    => "208s",
        ),
    );
    /**
     * @var array
     */
    public $XM_to_PHP = array(
        "O" => "B",
        "B" => "b",
        "I" => "h",
        "L" => "i",
        "X" => "q", //Not yet implemented in my Struct.php
        "F" => "f",
        "D" => "d",

    );

    public function __construct()
    {
        $this->struct = new \Desean1625\Struct();
    }
/**
 * @param $filename
 */
    public function readheader($filename)
    {
        $fh       = fopen($filename, "rw");
        $rawhdr   = fread($fh, 512);
        $head_rep = substr($rawhdr, 4, 4);
        if ($head_rep != "IEEE" && $head_rep != "EEEI") {
            throw new Exception($head_rep . " formatted BLUE headers not supported,
					convert to IEEE or EEEI format first");
        }
        $be               = ($head_rep == "EEEI") ? "<" : ">";
        $hdr              = $this->unpack_header($rawhdr, $be);
        $type             = substr($hdr['type'], 0, 1);
        $hdr              = array_merge($hdr, $this->unpackAdjunct($rawhdr, $be, $type));
        $hdr['file_name'] = basename($filename);
        $ext_start        = $hdr['ext_start'] * 512;
        fseek($fh, $ext_start);
        $ext_headers = fread($fh, $hdr['ext_size']);
        //$hdr['keywords'] = $this->unpack_ext_header($hdr['keywords'],$be);
        $hdr['ext_header'] = $this->unpack_ext_header($ext_headers, $be);
        print_r($hdr);
        return $hdr;
    }

/**
 * @param $rawhdr
 * @param $be
 * @return mixed
 */
    public function unpack_header($rawhdr, $be)
    {

        $hdr = $this->struct->unpack($be . implode("", $this->bstructs['HEADER']), $rawhdr);
        $hdr = array_combine(array_keys($this->bstructs['HEADER']), $hdr);
        return $hdr;
    }

/**
 * @param $rawhdr
 * @param $type
 * @return mixed
 */
    public function unpackAdjunct($rawhdr, $be, $type)
    {

        $hdr = $this->struct->unpack(
            $be . implode("", $this->bstructs["ADJUNCTT$type"]),
            substr($rawhdr, 256, 512)
        );
        $hdr = array_combine(array_keys($this->bstructs["ADJUNCTT$type"]), $hdr);
        return $hdr;
    }
    /**
     * @param $buf
     * @param $endian
     */
    public function unpack_ext_header($buf, $endian)
    {
        $struct   = $this->struct;
        $lbuf     = strlen($buf);
        $keywords = array();
        $martes   = $lbuf >= 8 && $struct->unpack($endian . 'i', substr($buf, 4, 8))[0] <= 128;
        if ($martes) {
            $kpacking = $endian . "iii";
            $format   = "A";
        } else {
            $kpacking = $endian . "ihbc";
        }
        $ii = 0;
        while ($ii < $lbuf) {
            if ($martes) {
                $itag                       = $ii + 12;
                list($lnext, $ltag, $ldata) = $struct->unpack($endian . $kpacking, substr($buf, $ii, $itag));
            } else {
                $idata = $ii + 8;

                list($lkey, $lextra, $ltag, $format) = $struct->unpack($endian . $kpacking, substr($buf, $ii, $idata));

                $ldata = $lkey - $lextra;
                $itag  = $idata + $ldata;
                $tag   = substr($buf, $itag, $ltag);
                $data  = substr($buf, $idata, $ldata);

            }
            if ($format != "A") {
                $data = $this->struct->unpack($endian . $this->XM_to_PHP[$format], $data)[0];
            }
            $keywords[$tag] = $data;
            $ii += $lkey;

        }
        return $keywords;
    }
}
