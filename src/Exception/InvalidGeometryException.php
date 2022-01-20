<?php
namespace GeoPHP\Exception;

/**
 * Class InvalidGeometryException
 * Invalid geometry means that it doesn't meet the basic requirements to be valid
 * Eg. a LineString with only one point
 *
 * @package GeoPHP\Exception
 */
class InvalidGeometryException extends \Exception
{
    
}
