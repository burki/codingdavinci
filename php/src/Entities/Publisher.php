<?php
namespace Entities;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // for Gedmo extensions annotations

/**
* The Publisher on the NS-Verbotsliste
*
* @ORM\Table(name="publisher", options={"engine":"MyISAM"})
* @ORM\Entity
*/
class Publisher extends Base
{
    /**
    * @ORM\Column(type="integer", nullable=false)
    * @ORM\Id
    * @ORM\GeneratedValue(strategy="IDENTITY")
    */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $status = 0;

    /**
    * @ORM\Column(name="preferred_name", type="string", nullable=true)
    */
    protected $preferredName;

    /**
    * @ORM\Column(name="variant_names", type="string", nullable=true)
    */
    protected $variantNames;


    /**
    * @ORM\Column(name="biographical_or_historical_information", type="string", nullable=true)
    */
    protected $biographicalOrHistoricalInformation;

    /**
    * @ORM\Column(name="date_of_establishment", type="string", nullable=true)
    */
    protected $dateOfEstablishment;

    /**
    * @ORM\Column(name="date_of_termination", type="string", nullable=true)
    */
    protected $dateOfTermination;

    /**
    * @ORM\Column(name="place_of_business", type="simple_array", nullable=true)
    */
    protected $placeOfBusiness;

    /**
    * @ORM\Column(name="gnd_place_of_business", type="simple_array", nullable=true)
    */
    protected $gndPlaceOfBusiness;

    /**
     * @ORM\Column(name="list_row", type="integer", nullable=true)
     */
    protected $listRow;

    /**
    * @ORM\Column(name="complete_works", type="integer")
    */
    protected $completeWorks = 0;

    /**
    * @ORM\Column(type="string", nullable=true)
    */
    protected $gnd;

    /**
     * @ORM\OneToMany(targetEntity="Publication", mappedBy="publishedBy")
     * @ORM\OrderBy({"issued" = "ASC"})
     */
    private $publications;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @Gedmo\Timestampable(on="change", field={"surname", "forename", "gnd"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="changed_at", type="datetime")
     */
    protected $changedAt;
}
