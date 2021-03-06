<?php
/**
 * Project: 
 * Contenido Content Management System
 * 
 * Description: 
 * Step 6 of installation
 * 
 * Requirements: 
 * @con_php_req 5
 *
 * @package    ContenidoBackendArea
 * @version    1.0.1
 * @author     Rudi Bieller
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * 
 * 
 * 
 * {@internal 
 *   created  2008-03-14
 *   modified 2008-07-07, bilal arslan, added security fix
 *
 *   $Id$:
 * }}
 * 
 */
 if(!defined('CON_FRAMEWORK')) {
                die('Illegal call');
}

checkAndInclude("steps/forms/additionalplugins.php");

$cSetupSetupSummary = new cSetupAdditionalPlugins(6, "migration5", "migration7");
$cSetupSetupSummary->render();
?>