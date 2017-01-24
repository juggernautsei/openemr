<?php
/**
 * Product Registration entity.
 *
 * Copyright (C) 2017 Sherwin Gaddis <sherwingaddis@gmail.com>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://opensource.org/licenses/gpl-license.php>;.
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @link    http://www.open-emr.org
 */

namespace entities;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;


	/**
	 * @Table(name="product_registration")
	 * @Entity(repositoryClass="repositories\ProductRegistrationRepository")
	 */

	class ProductRegistration {
		/**
		 * Default constructor.
		 */
		public function __construct(){

		}

		/**
		 * @Column(name="p_registration_id"), type="char", length=36 nullable=false, options={"default" : 0})
		 */
		 private $registration_id;

		/**
		 * @Column(name="p_email"), type="varchar", length=255 nullable=false, options={"default" : 0})
		 */
		 private $email;

		/**
		 * @Column(name="p_opt_out"), type="tinyint", length=1 nullable=false, options={"default" : 0})
		 */
	     private $opt_out;

	    /**
	     * Getter for registration_id.
	     *
	     * return registration_id number
	     */
	    public function getRegistrationid() {
	        return $this->registration_id;
	    }

	    /**
	     * Setter for registration_id.
	     *
	     * @param registration_id string
	     */
	    public function setRegistrationid($value) {
	        $this->registration_id = $value;
	    }     

	    /**
	     * Getter for email.
	     *
	     * return email string
	     */
	    public function getEmail() {
	        return $this->email;
	    }

	    /**
	     * Setter for email.
	     *
	     * @param email string
	     */
	    public function setEmail($value) {
	        $this->email = $value;
	    }     

	    /**
	     * Getter for opt_out.
	     *
	     * return opt_out string
	     */
	    public function getOptout() {
	        return $this->opt_out;
	    }

	    /**
	     * Setter for opt_out.
	     *
	     * @param opt_out number
	     */
	    public function setOptout($value) {
	        $this->opt_out = $value;
	    }

	    /**
	     * ToString of the entire object.
	     *
	     * @return object as string
	     */
	    public function __toString() {
	        return "registration_id: '" . $this->getRegistrationid() . "' " .
	               "email: '" . $this->getEmail() . "' " .
	               "opt_out" . $this->getOptout() . "' " ;
	    }
	}


