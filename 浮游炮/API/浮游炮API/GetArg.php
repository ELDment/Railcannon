<?php
class Arg
{
    public function Parse($arg)
    {
        if ($this -> IsNull($_POST[$arg]))
        {
            if (!($this -> IsNull($_GET[$arg])))
            {
                return trim($_GET[$arg]);
            }else{
                return 'Invaild Arg';
            }
        }else{
            return trim($_POST[$arg]);
        }
    }
    protected function IsNull($arg)
    {
        return (
            (is_null(trim($arg))) ? true : (
                (trim($arg) == '') ? true : (
                    (mb_strlen(trim($arg), 'utf-8') == 0) ? true : false
                )
            )
        );
    }
}
?>