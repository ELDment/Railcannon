<?php
namespace ELDment;
class HandleException extends \Exception
{
    function  __construct($message)
    {
        $fileStream = fopen(__DIR__ . "/debugLog/" . date("Ymd") . ".php", "a+");
        fputs($fileStream, "<? ". date("Y-m-d H:i:s") . " ?> ");
        fputs($fileStream, $message);
        fputs($fileStream, "\n");
        fclose($fileStream);
        http_response_code(502);
        exit(502);
    }
}
?>