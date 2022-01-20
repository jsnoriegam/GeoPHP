<?php
namespace GeoPHP\Adapter;

/**
 * Defines the available GPX types and their allowed elements following the GPX specification
 *
 * @see http://www.topografix.com/gpx/1/1/
 * @package GeoPHP\Adapter
 */
class GpxTypes
{

    /**
     * @var array<string> allowed elements in <gpx>
     * @see http://www.topografix.com/gpx/1/1/#type_gpxType
     */
    const GPX_TYPE_ELEMENTS = [
        'metadata', 'wpt', 'rte', 'trk'
    ];

    /**
     * @var array<string> allowed elements in <trk>
     * @see http://www.topografix.com/gpx/1/1/#type_trkType
     */
    const TRK_TYPE_ELEMENTS = [
        'name', 'cmt', 'desc', 'src', 'link', 'number', 'type'
    ];

    /**
     * same as TRK_TYPE_ELEMENTS
     * @var array<string> Allowed elements in <rte>
     * @see http://www.topografix.com/gpx/1/1/#type_rteType
     */
    const RTE_TYPE_ELEMENTS = [
        'name', 'cmt', 'desc', 'src', 'link', 'number', 'type'
    ];
    
    /**
     * @var array<string> allowed elements in <wpt>
     * @see http://www.topografix.com/gpx/1/1/#type_wptType
     */
    const WPT_TYPE_ELEMENTS = [
        'ele', 'time', 'magvar', 'geoidheight', 'name', 'cmt', 'desc', 'src', 'link', 'sym', 'type',
        'fix', 'sat', 'hdop', 'vdop', 'pdop', 'ageofdgpsdata', 'dgpsid'
    ];

    /**
     * @var array<string> same as WPT_TYPE_ELEMENTS
     */
    const TRKPT_TYPE_ELEMENTS = [
        'ele', 'time', 'magvar', 'geoidheight', 'name', 'cmt', 'desc', 'src', 'link', 'sym', 'type',
        'fix', 'sat', 'hdop', 'vdop', 'pdop', 'ageofdgpsdata', 'dgpsid'
    ];

    /**
     * @var array<string> Same as WPT_TYPE_ELEMENTS
     */
    const RTEPT_TYPE_ELEMENTS = [
        'ele', 'time', 'magvar', 'geoidheight', 'name', 'cmt', 'desc', 'src', 'link', 'sym', 'type',
        'fix', 'sat', 'hdop', 'vdop', 'pdop', 'ageofdgpsdata', 'dgpsid'
    ];

    /**
     * @var array<string> Allowed elements in <metadata>
     * @see http://www.topografix.com/gpx/1/1/#type_metadataType
     */
    const METADATA_TYPE_ELEMENTS = [
        'name', 'desc', 'author', 'copyright', 'link', 'time', 'keywords', 'bounds'
    ];
    
    /**
     * @var array<string>
     */
    protected $allowedGpxTypeElements;
    
    /**
     * @var array<string>
     */
    protected $allowedTrkTypeElements;
    
    /**
     * @var array<string>
     */
    protected $allowedRteTypeElements;
    
    /**
     * @var array<string>
     */
    protected $allowedWptTypeElements;
    
    /**
     * @var array<string>
     */
    protected $allowedTrkptTypeElements;
    
    /**
     * @var array<string>
     */
    protected $allowedRteptTypeElements;
    
    /**
     * @var array<string>
     */
    protected $allowedMetadataTypeElements;

    /**
     * GpxTypes constructor.
     *
     * @param array<string, array|null> $allowedElements Which elements can be used in each GPX type
     *              If not specified, every element defined in the GPX specification can be used
     *              Can be overwritten with an associative array, with type name in keys.
     *              eg.: ['wptType' => ['ele', 'name'], 'trkptType' => ['ele'], 'metadataType' => null]
     */
    public function __construct(array $allowedElements = [])
    {
        $this->allowedGpxTypeElements = self::GPX_TYPE_ELEMENTS;
        $this->allowedTrkTypeElements = self::TRK_TYPE_ELEMENTS;
        $this->allowedRteTypeElements = self::RTE_TYPE_ELEMENTS;
        $this->allowedWptTypeElements = self::WPT_TYPE_ELEMENTS;
        $this->allowedTrkptTypeElements = self::TRKPT_TYPE_ELEMENTS;
        $this->allowedRteptTypeElements = self::RTEPT_TYPE_ELEMENTS;
        $this->allowedMetadataTypeElements = self::METADATA_TYPE_ELEMENTS;

        foreach ($allowedElements as $type => $elements) {
            $elements = is_array($elements) ? $elements : [$elements];
            $this->{'allowed' . ucfirst($type) . 'Elements'} = [];
            
            $constName = $type . '_TYPE_ELEMENTS';
            foreach (self::$$constName as $availableType) {
                if (in_array($availableType, $elements)) {
                    $this->{'allowed' . ucfirst($type) . 'Elements'}[] = $availableType;
                }
            }
        }
    }

    /**
     * Returns an array of allowed elements for the given GPX type
     * eg. "gpxType" returns ['metadata', 'wpt', 'rte', 'trk']
     *
     * @param string $type One of the following GPX types: gpxType, trkType, rteType, wptType, trkptType, rteptType, metadataType
     * @return array<string>
     */
    public function get($type): array
    {
        $propertyName = 'allowed' . ucfirst($type) . 'Elements';

        return isset($this->{$propertyName}) ? $this->{$propertyName} : [];
    }
}
