<?php
/**
 * Product registration repository.

 * Copyright (C) 2016 sherwin gaddis <sherwin@openmedpractice.com>
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
 * @author  sherwin gaddis <sherwin@openmedpractice.com>
 * @link    http://www.openmedpractice.com
 */


namespace repositories;

use entities\Product_registration;
use Doctrine\ORM\EntityRepository;

class ProductRegistrationRepository extends EntityRepository{

      /**
       * Creates the sole entry that the product has been registered
       * The table has one unique key
       * The table should have only one entry
       * @param $registered
       * 
       */
    public function create(Product_registration $registered){
    	$this->_em->presist($registered);
    	$this->_em->flush(); //tells doctrine to write info to table
    	return true;
    } 
}


?>