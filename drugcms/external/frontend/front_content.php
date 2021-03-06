<?php
/**
 * Project:
 * Contenido Content Management System
 *
 * Description:
 * This file handles the view of an article.
 *
 * To handle the page we use the Database Abstraction Layer, the Session, Authentication and Permissions Handler of the
 * PHPLIB application development toolkit.
 *
 * The Client Id and the Language Id of an article will be determined depending on file __FRONTEND_PATH__/config.php where
 * $load_lang and $load_client are defined.
 * Depending on http globals via e.g. front_content.php?idcat=41&idart=34
 * the most important Contenido globals $idcat (Category Id), $idart (Article Id), $idcatart, $idartlang will be determined.
 *
 * The article can be displayed and edited in the Backend or the Frontend.
 * The attributes of an article will be considered (an article can be online, offline or protected ...).
 *
 * It is possible to customize the behavior by including the file __FRONTEND_PATH__/config.local.php or
 * the file __FRONTEND_PATH__/config.after.php
 *
 * If you use 'Frontend User' for protected areas, the category access permission will by handled via the
 * Contenido Extension Chainer.
 *
 * Finally the 'code' of an article will by evaluated and displayed.
 *
 * Requirements:
 * @con_php_req 5.0
 * @con_notice If you edit this file you must synchronise the files
 * - ./cms/front_content.php
 * - ./drugcms/external/backendedit/front_content.php
 * - ./drugcms/external/frontend/front_content.php
 *
 *
 * @package    Contenido Frontend
 * @version    4.8
 * @author     Olaf Niemann, Jan Lengowski, Timo A. Hummel et al.
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since contenido release <= 4.6
 *
 * {@internal
 *   created 2003-01-21
 *   modified 2008-06-16, H. Librenz, Hotfix: checking for potential unsecure call
 *   modified 2008-06-26, Frederic Schneider, add security fix
 *   modified 2008-07-02, Frederic Schneider, add more security fixes and include security_class
 *   modified 2008-08-29, Murat Purc, new way to execute chains
 *   modified 2008-09-07, Murat Purc, new chain 'Contenido.Frontend.AfterLoadPlugins'
 *   modified 2008-11-11, Andreas Lindner, added additional option to CEC_Hook::setConditions for frontend user acccess
 *   modified 2008-11-11, Andreas Lindner, Fixed typo in var name $iLangCheck (missing $)
 *   modified 2008-11-11, Andreas Lindner,        
 *   modified 2008-11-18, Timo Trautmann: in backendeditmode also check if logged in backenduser has permission to view preview of page 
 *   modified 2008-11-18, Murat Purc, add usage of Contenido_Url to create urls to frontend pages
 *   modified 2008-12-23, Murat Purc, fixed problems with Contenido_Url
 *   modified 2009-01-13, Murat Purc, changed handling of internal redirects
 *   modified 2009-03-02, Andreas Lindner, prevent $lang being wrongly set to 0 
 *   modified 2009-04-16, OliverL, check return from Contenido.Frontend.HTMLCodeOutput
 *   modified 2009-10-23, Murat Purc, removed deprecated function (PHP 5.3 ready)
 *   modified 2009-10-27, Murat Purc, fixed/modified CEC_Hook, see [#CON-256]
 *   modified 2010-05-20, Murat Purc, moved security checks into startup process, see [#CON-307]
 *   modified 2010-09-23, Murat Purc, fixed $encoding handling, see [#CON-305]
 *   modified 2011-02-07, Dominik Ziegler, added exit after redirections to force their execution
 *   modified 2011-02-10, Dominik Ziegler, moved function declaration of IP_match out of front_content.php
 *
 *   $Id$:
 * }}
 *
 */

if (!defined("CON_FRAMEWORK")) {
    define("CON_FRAMEWORK", true);
}

$contenido_path = '';
# include the config file of the frontend to init the Client and Language Id
include_once ("config.php");

# Contenido startup process
include_once ($contenido_path . 'includes/startup.php');

cInclude("includes", "functions.con.php");
cInclude("includes", "functions.con2.php");
cInclude("includes", "functions.api.php");
cInclude("includes", "functions.pathresolver.php");

if ($cfg["use_pseudocron"] == true)
{
    /* Include cronjob-Emulator */
    $oldpwd = getcwd();
    chdir($cfg["path"]["contenido"].$cfg["path"]["cronjobs"]);
    cInclude("includes", "pseudo-cron.inc.php");
    chdir($oldpwd);
}

/*
 * Initialize the Database Abstraction Layer, the Session, Authentication and Permissions Handler of the
 * PHPLIB application development toolkit
 * @see http://sourceforge.net/projects/phplib
 */
if ($contenido)
{
    //Backend
    page_open(array ('sess' => 'Contenido_Session', 'auth' => 'Contenido_Challenge_Crypt_Auth', 'perm' => 'Contenido_Perm'));
    i18nInit($cfg["path"]["contenido"].$cfg["path"]["locale"], $belang);
}
else
{
    //Frontend
    page_open(array ('sess' => 'Contenido_Frontend_Session', 'auth' => 'Contenido_Frontend_Challenge_Crypt_Auth', 'perm' => 'Contenido_Perm'));
}

/**
 * Bugfix
 * @see http://contenido.org/forum/viewtopic.php?t=18291
 *
 * added by H. Librenz (2007-12-07)
 */
//includePluginConf();
/**
 * fixed bugfix - using functions brokes variable scopes!
 *
 * added by H. Librenz (2007-12-21) based on an idea of A. Lindner
 */
require_once $cfg['path']['contenido'] . $cfg['path']['includes'] . 'functions.includePluginConf.php';

$db = new DB();

$sess->register("cfgClient");
$sess->register("errsite_idcat");
$sess->register("errsite_idart");
$sess->register("encoding");

if ($cfgClient["set"] != "set")
{
    rereadClients();
}

# Check if this request is for a compressed file
if ($_GET['action'] == 'get_compressed') {
    
    # Get the calling parameters
    $sFilename      = ((isset($_GET['f'])) ? $_GET['f'] : $_GET['amp;f']);
    $sContentType   = ((isset($_GET['c'])) ? $_GET['c'] : $_GET['amp;c']);
    
    # Check if the file was already downloaded
    $sFileName = substr($sFilename, 0, strrpos($sFilename, '.'));
    $sFileDateTime = substr($sFileName, -14);
    if (date('YmdHis', strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) >= $sFileDateTime) {
        # Return a "304 Not Modified" header
        header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
    } else {
        # Output the file using the class output() function
        Output_Compressor::output($cfgClient[$client]['path']['frontend'] . 'cache/', $sFilename, $sContentType);
    }
    
    # Don't do anything else
    exit();
}

// Call hook after plugins are loaded, added by Murat Purc, 2008-09-07
CEC_Hook::execute('Contenido.Frontend.AfterLoadPlugins');

if (!isset($encoding) || !is_array($encoding) || count($encoding) == 0)
{
    // get encodings of all languages
    $encoding = array();
    $sql = "SELECT idlang, encoding FROM " . $cfg["tab"]["lang"];
    $db->query($sql);
    while ($db->next_record()) {
        $encoding[$db->f('idlang')] = $db->f('encoding');
    }
}


// Check frontend globals
// @TODO: Should be outsourced into startup process but requires a better detection (frontend or backend)
Contenido_Security::checkFrontendGlobals();


// update urlbuilder set http base path 
Contenido_Url::getInstance()->getUrlBuilder()->setHttpBasePath($cfgClient[$client]['htmlpath']['frontend']);


// Initialize language
if (!isset($lang)) {

    // if there is an entry load_lang in frontend/config.php use it, else use the first language of this client
    if(isset($load_lang)){
        // load_client is set in frontend/config.php
        $lang = $load_lang;
    }else{

        $sql = "SELECT
                    B.idlang
                FROM
                    ".$cfg["tab"]["clients_lang"]." AS A,
                    ".$cfg["tab"]["lang"]." AS B
                WHERE
                    A.idclient='".Contenido_Security::toInteger($client)."' AND
                    A.idlang = B.idlang
                LIMIT
                    0,1";

        $db->query($sql);
        $db->next_record();

        $lang = $db->f("idlang");
    }
}

if (!$sess->is_registered("lang") ) $sess->register("lang");
if (!$sess->is_registered("client") ) $sess->register("client");

if (in_array(getEffectiveSetting('modules_in_files', 'use', 'false'), array('true', '1'))) {
    $force = true;
}

if (isset ($username))
{
    $auth->login_if(true);
}

/*
 * Send HTTP header with encoding
 */
header("Content-Type: text/html; charset={$encoding[$lang]}");

/*
 * if http global logout is set e.g. front_content.php?logout=true
 * log out the current user.
 */
if (isset ($logout))
{
    $auth->logout(true);
    $auth->unauth(true);
    $auth->auth["uname"] = "nobody";
}

/*
 * local configuration
 */
if (file_exists("config.local.php"))
{
    @ include ("config.local.php");
}

/*
 * If the path variable was passed, try to resolve it to a Category Id
 * e.g. front_content.php?path=/company/products/
 */
if (isset($path) && strlen($path) > 1)
{
    /* Which resolve method is configured? */
    if ($cfg["urlpathresolve"] == true)
    {

        $iLangCheck = 0;
        $idcat = prResolvePathViaURLNames($path, $iLangCheck);

    }
    else
    {
        $iLangCheck = 0;

        $idcat = prResolvePathViaCategoryNames($path, $iLangCheck);
        if(($lang != $iLangCheck) && ((int)$iLangCheck != 0)){
            $lang = $iLangCheck;
        }

    }
}

// error page
$aParams = array (
    'client' => $client, 'idcat' => $errsite_idcat[$client], 'idart' => $errsite_idart[$client], 
    'lang' => $lang, 'error'=> '1'
);
$errsite = 'Location: ' . Contenido_Url::getInstance()->buildRedirect($aParams);


/*
 * Try to initialize variables $idcat, $idart, $idcatart, $idartlang
 * Note: These variables can be set via http globals e.g. front_content.php?idcat=41&idart=34&idcatart=35&idartlang=42
 * If not the values will be computed.
 */
if ($idart && !$idcat && !$idcatart)
{
    /* Try to fetch the first idcat */
    $sql = "SELECT idcat FROM ".$cfg["tab"]["cat_art"]." WHERE idart = '".Contenido_Security::toInteger($idart)."'";
    $db->query($sql);

    if ($db->next_record())
    {
        $idcat = $db->f("idcat");
    }
}

unset ($code);
unset ($markscript);

if (!$idcatart)
{
    if (!$idart)
    {
        if (!$idcat)
        {
            # Note: In earlier Contenido versions the information if an article is startarticle of a category has been stored
            # in relation con_cat_art.
            if ($cfg["is_start_compatible"] == true)
            {
                $sql = "SELECT
                            idart,
                            B.idcat
                        FROM
                            ".$cfg["tab"]["cat_art"]." AS A,
                            ".$cfg["tab"]["cat_tree"]." AS B,
                            ".$cfg["tab"]["cat"]." AS C
                        WHERE
                            A.idcat=B.idcat AND
                            B.idcat=C.idcat AND
                            is_start='1' AND
                            idclient='".Contenido_Security::toInteger($client)."'
                        ORDER BY
                            idtree ASC";
            }
            else
            {
                # Note: Now the information if an article is startarticle of a category is stored in relation con_cat_lang.
                $sql = "SELECT
                            A.idart,
                            B.idcat
                        FROM
                            ".$cfg["tab"]["cat_art"]." AS A,
                            ".$cfg["tab"]["cat_tree"]." AS B,
                            ".$cfg["tab"]["cat"]." AS C,
                            ".$cfg["tab"]["cat_lang"]." AS D,
                            ".$cfg["tab"]["art_lang"]." AS E
                        WHERE
                            A.idcat=B.idcat AND
                            B.idcat=C.idcat AND
                            D.startidartlang = E.idartlang AND
                            D.idlang='".Contenido_Security::toInteger($lang)."' AND
                            E.idart=A.idart AND
                            E.idlang='".Contenido_Security::toInteger($lang)."' AND
                            idclient='".Contenido_Security::toInteger($client)."'
                        ORDER BY
                            idtree ASC";
            }

            $db->query($sql);

            if ($db->next_record()) {
                $idart = $db->f("idart");
                $idcat = $db->f("idcat");
            } else {
                if($contenido) {
                    cInclude("includes", "functions.i18n.php");
                    die(i18n("No start article for this category"));
                } else {
                    if($error == 1) {
                        die("Fatal error: Could not display error page. Error to display was: 'No start article in this category'");
                    } else {
                        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                        header("Status: 404 Not Found");
                        $_SERVER['REDIRECT_STATUS'] = 404;
                        header($errsite);
						exit;
                    }
                }
            }
        } else {
            $idart = -1;
            if ($cfg["is_start_compatible"] == true)
            {
                $sql = "SELECT idart FROM ".$cfg["tab"]["cat_art"]." WHERE idcat='".Contenido_Security::toInteger($idcat)."' AND is_start='1'";
                $db->query($sql);

                if ($db->next_record())
                {
                    $idart = $db->f("idart");
                }
            }
            else
            {
                $sql = "SELECT startidartlang FROM ".$cfg["tab"]["cat_lang"]." WHERE idcat='".Contenido_Security::toInteger($idcat)."' AND idlang='".Contenido_Security::toInteger($lang)."'";
                $db->query($sql);

                if ($db->next_record())
                {
                    if ($db->f("startidartlang") != 0)
                    {
                        $sql = "SELECT idart FROM ".$cfg["tab"]["art_lang"]." WHERE idartlang='".Contenido_Security::toInteger($db->f("startidartlang"))."'";
                        $db->query($sql);
                        $db->next_record();
                        $idart = $db->f("idart");
                    }
                }
            }

            if ($idart != -1)
            {
            }
            else
            {
                // error message in backend
                if ($contenido)
                {
                    cInclude("includes", "functions.i18n.php");
                    die(i18n("No start article for this category"));
                }
                else
                {
                    if ($error == 1)
                    {
                        echo "Fatal error: Could not display error page. Error to display was: 'No start article in this category'";
                    }
                    else
                    {
                        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                        header("Status: 404 Not Found");
                        $_SERVER['REDIRECT_STATUS'] = 404;
                        header($errsite);
						exit;
                    }
                }
            }
        }
    }
}
else
{
    $sql = "SELECT idcat, idart FROM ".$cfg["tab"]["cat_art"]." WHERE idcatart='".Contenido_Security::toInteger($idcatart)."'";

    $db->query($sql);
    $db->next_record();

    $idcat = $db->f("idcat");
    $idart = $db->f("idart");
}

/* Get idcatart */
if (($idart) && ($idcat) && (!$idcatart))
{
    $sql = "SELECT idcatart FROM ".$cfg["tab"]["cat_art"]." WHERE idart = '".Contenido_Security::toInteger($idart)."' AND idcat = '".Contenido_Security::toInteger($idcat)."'";

    $db->query($sql);
    $db->next_record();

    $idcatart = $db->f("idcatart");
}

$idartlang = getArtLang($idart, $lang);

if ($idartlang === false)
{
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    header("Status: 404 Not Found");
    $_SERVER['REDIRECT_STATUS'] = 404;
    header($errsite);
	exit;
}

/*
 * removed database roundtrip for checking
 * if cache is enabled
 * CON-115
 * 2008-06-25 Thorsten Granz
 */
// START: concache, murat purc
if ($cfg["cache"]["disable"] != '1') {
    cInclude('frontend', 'includes/concache.php');
    $oCacheHandler = new cConCacheHandler($GLOBALS['cfgConCache'], $db);
    $oCacheHandler->start($iStartTime); // $iStartTime ist optional und ist die startzeit des scriptes, z. b. am anfang von fron_content.php
}
// END: concache


##############################################
# BACKEND / FRONTEND EDITING
##############################################

/**
 * If user has drugcms-backend rights.
 * $contenido <==> the drugcms backend session as http global
 * In Backend: e.g. drugcms/index.php?contenido=dac651142d6a6076247d3afe58c8f8f2
 * Can also be set via front_content.php?contenido=dac651142d6a6076247d3afe58c8f8f2
 *
 * Note: In backend the file drugcms/external/backendedit/front_content.php is included!
 * The reason is to avoid cross-site scripting errors in the backend, if the backend domain differs from
 * the frontend domain.
 */
if ($contenido)
{
    $perm->load_permissions();

    /* Change mode edit / view */
    if (isset ($changeview))
    {
        $sess->register("view");
        $view = $changeview;
    }

    $col = new InUseCollection;

    if ($overrideid != "" && $overridetype != "")
    {
        $col->removeItemMarks($overridetype, $overrideid);
    }
    /* Remove all own marks */
    $col->removeSessionMarks($sess->id);
    /* If the override flag is set, override a specific InUseItem */

    list ($inUse, $message) = $col->checkAndMark("article", $idartlang, true, i18n("Article is in use by %s (%s)"), true, $cfg['path']['contenido_fullhtml']."external/backendedit/front_content.php?changeview=edit&action=con_editart&idartlang=$idartlang&type=$type&typenr=$typenr&idart=$idart&idcat=$idcat&idcatart=$idcatart&client=$client&lang=$lang");

    $sHtmlInUse = '';
    $sHtmlInUseMessage = '';
    if ($inUse == true)
    {
        $disabled = 'disabled="disabled"';
        $sHtmlInUseCss = '<link rel="stylesheet" type="text/css" href="'.$cfg['path']['contenido_fullhtml'].'styles/inuse.css" />';
        $sHtmlInUseMessage = $message;
    }

    $sql = "SELECT locked FROM ".$cfg["tab"]["art_lang"]." WHERE idart='".Contenido_Security::toInteger($idart)."' AND idlang = '".Contenido_Security::toInteger($lang)."'";
    $db->query($sql);
    $db->next_record();
    $locked = $db->f("locked");
    if ($locked == 1)
    {
        $inUse = true;
        $disabled = 'disabled="disabled"';
    }

    // CEC to check if the user has permission to edit articles in this category
    CEC_Hook::setBreakCondition(false, true); // break at "false", default value "true"
    $allow = CEC_Hook::executeWhileBreakCondition(
        'Contenido.Frontend.AllowEdit', $lang, $idcat, $idart, $auth->auth['uid']
    );

    if ($perm->have_perm_area_action_item("con_editcontent", "con_editart", $idcat) && $inUse == false && $allow == true)
    {
        /* Create buttons for editing */
        $edit_preview = '<table cellspacing="0" cellpadding="4" border="0">';

        if ($view == "edit")
        {
            $edit_preview = '<tr>
                                <td width="18">
                                    <a title="Preview" style="font-family: Verdana; font-size: 10px; color: #000000; text-decoration: none" href="'.$sess->url("front_content.php?changeview=prev&idcat=$idcat&idart=$idart").'"><img src="'.$cfg["path"]["contenido_fullhtml"].$cfg["path"]["images"].'but_preview.gif" alt="Preview" title="Preview" border="0"></a>
                                </td>
                                <td width="18">
                                    <a title="Preview" style="font-family: Verdana; font-size: 10px; color: #000000; text-decoration: none" href="'.$sess->url("front_content.php?changeview=prev&idcat=$idcat&idart=$idart").'">Preview</a>
                                </td>
                            </tr>';
        }
        else
        {
            $edit_preview = '<tr>
                                <td width="18">
                                    <a title="Preview" style="font-family: Verdana; font-size: 10px; color: #000000; text-decoration: none" href="'.$sess->url("front_content.php?changeview=edit&idcat=$idcat&idart=$idart").'"><img src="'.$cfg["path"]["contenido_fullhtml"].$cfg["path"]["images"].'but_edit.gif" alt="Preview" title="Preview" border="0"></a>
                                </td>
                                <td width="18">
                                    <a title="Preview" style="font-family: Verdana; font-size: 10px; color: #000000; text-decoration: none" href="'.$sess->url("front_content.php?changeview=edit&idcat=$idcat&idart=$idart").'">Edit</a>
                                </td>
                            </tr>';
        }

        /* Display articles */
        if ($cfg["is_start_compatible"] == true)
        {
            $sql = "SELECT idart, is_start FROM ".$cfg["tab"]["cat_art"]." WHERE idcat='".Contenido_Security::toInteger($idcat)."' ORDER BY idart";

            $db->query($sql);
        }
        else
        {
            $sql = "SELECT idart FROM ".$cfg["tab"]["cat_art"]." WHERE idcat='".Contenido_Security::toInteger($idcat)."' ORDER BY idart";

            $db->query($sql);
        }

        $a = 1;

        $edit_preview .= '<tr><td colspan="2"><table cellspacing="0" cellpadding="2" border="0"></tr><td style="font-family: verdana; font-size:10; color:#000000; text-decoration:none">Articles in category:<br>';

        while ($db->next_record() && ($db->affected_rows() != 1))
        {

            $class = "font-family:'Verdana'; font-size:10; color:#000000; text-decoration: underline; font-weight:normal";
            if (!isset ($idart))
            {
                if (isStartArticle(getArtLang($idart, $lang), $idcat, $lang))
                {
                    $class = "font-family: verdana; font-size:10; color:#000000; text-decoration: underline ;font-weight:bold";
                }
            }
            else
            {
                if ($idart == $db->f("idart"))
                {
                    $class = "font-family: verdana; font-size:10; color:#000000; text-decoration: underline; font-weight:bold";
                }
            }

            $edit_preview .= "<a style=\"$class\" href=\"".$sess->url("front_content.php?idart=".$db->f("idart")."&idcat=$idcat")."\">$a</a>&nbsp;";
            $a ++;
        }

        $edit_preview .= '</td></tr></table></td></tr></table>';

    }

} // end if $contenido


/* If mode is 'edit' and user has permission to edit articles in the current category  */
if ($inUse == false && $allow == true && $view == "edit" && ($perm->have_perm_area_action_item("con_editcontent", "con_editart", $idcat)))
{
    cInclude("includes", "functions.tpl.php");
    cInclude("includes", "functions.con.php");
    include ($cfg["path"]["contenido"].$cfg["path"]["includes"]."include.con_editcontent.php");
}
else
{

##############################################
# FRONTEND VIEW
##############################################

    /* Mark submenuitem 'Preview' in the Contenido Backend (Area: Contenido --> Articles --> Preview) */
    if ($contenido)
    {
        $markscript = markSubMenuItem(4, true);
    }

    unset($edit); // disable editmode

    /* 'mode' is preview (Area: Contenido --> Articles --> Preview) or article displayed in the front-end */
    $sql = "SELECT
                createcode
            FROM
                ".$cfg["tab"]["cat_art"]."
            WHERE
                idcat = '".Contenido_Security::toInteger($idcat)."' AND
                idart = '".Contenido_Security::toInteger($idart)."'";

    $db->query($sql);
    $db->next_record();

    ##############################################
    # code generation
    ##############################################

    /* Check if code is expired, create new code if needed */
    if (($db->f("createcode") == 0) && ($force == 0))
    {
        $sql = "SELECT code FROM ".$cfg["tab"]["code"]." WHERE idcatart = '".Contenido_Security::toInteger($idcatart)."' AND idlang = '".Contenido_Security::toInteger($lang)."'";
        $db->query($sql);

        if ($db->num_rows() == 0)
        {
            /* Include here for performance reasons */
            cInclude("includes", "functions.tpl.php");

            conGenerateCode($idcat, $idart, $lang, $client);

            $sql = "SELECT code FROM ".$cfg["tab"]["code"]." WHERE idcatart = '".Contenido_Security::toInteger($idcatart)."' AND idlang = '".Contenido_Security::toInteger($lang)."'";
            $db->query($sql);
        }

        if ($db->next_record())
        {
            $code = stripslashes($db->f("code"));
        }
        else
        {
            if ($contenido)
                $code = "echo \"No code available.\";";
            else
            {
                if ($error == 1)
                {
                    echo "Fatal error: Could not display error page. Error to display was: 'No code available'";
                }
                else
                {
                    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                    header("Status: 404 Not Found");
                    $_SERVER['REDIRECT_STATUS'] = 404;
                    header($errsite);
					exit;
                }
            }
        }
    }
    else
    {
        $sql = "DELETE FROM ".$cfg["tab"]["code"]." WHERE idcatart = '".Contenido_Security::toInteger($idcatart)."'";
        $db->query($sql);

        cInclude("includes", "functions.con.php");
        cInclude("includes", "functions.tpl.php");
        cInclude("includes", "functions.mod.php");

        conGenerateCode($idcat, $idart, $lang, $client);

        $sql = "SELECT code FROM ".$cfg["tab"]["code"]." WHERE idcatart = '".Contenido_Security::toInteger($idcatart)."' AND idlang = '".Contenido_Security::toInteger($lang)."'";

        $db->query($sql);
        $db->next_record();

        $code = stripslashes($db->f("code"));
    }

    /*  Add mark Script to code if user is in the backend */
    $code = preg_replace("/<\/head>/i", "$markscript\n</head>", $code, 1);

    /* If article is in use, display notification */
    if ($sHtmlInUseCss && $sHtmlInUseMessage) {
        $code = preg_replace("/<\/head>/i", "$sHtmlInUseCss\n</head>", $code, 1);
        $code = preg_replace("/(<body[^>]*)>/i", "\${1}> \n $sHtmlInUseMessage", $code, 1);
    }

    /* Check if category is public */
    $sql = "SELECT public FROM ".$cfg["tab"]["cat_lang"]." WHERE idcat='".Contenido_Security::toInteger($idcat)."' AND idlang='".Contenido_Security::toInteger($lang)."'";

    $db->query($sql);
    $db->next_record();

    $public = $db->f("public");

    ##############################################
    # protected categories
    ##############################################
    if ($public == 0)
    {
        if ($auth->auth["uid"] == "nobody")
        {
            $sql = "SELECT user_id, value FROM ".$cfg["tab"]["user_prop"]." WHERE type='frontend' and name='allowed_ip'";
            $db->query($sql);

            while ($db->next_record())
            {
                $user_id = $db->f("user_id");

                $range = urldecode($db->f("value"));
                $slash = strpos($range, "/");

                if ($slash == false)
                {
                    $netmask = "255.255.255.255";
                    $network = $range;
                }
                else
                {
                    $network = substr($range, 0, $slash);
                    $netmask = substr($range, $slash +1, strlen($range) - $slash -1);
                }

                if (IP_match($network, $netmask, $_SERVER["REMOTE_ADDR"]))
                {
                    $sql = "SELECT idright
                            FROM ".$cfg["tab"]["rights"]." AS A,
                                 ".$cfg["tab"]["actions"]." AS B,
                                 ".$cfg["tab"]["area"]." AS C
                             WHERE B.name = 'front_allow' AND C.name = 'str' AND A.user_id = '".Contenido_Security::escapeDB($user_id, $db2)."' AND A.idcat = '".Contenido_Security::toInteger($idcat)."'
                                    AND A.idarea = C.idarea AND B.idaction = A.idaction";

                    $db2 = new DB();
                    $db2->query($sql);

                    if ($db2->num_rows() > 0)
                    {
                        $auth->auth["uid"] = $user_id;
                        $validated = 1;
                    }
                }
            }
            if ($validated != 1)
            {
                // CEC to check category access
                CEC_Hook::setBreakCondition(true, false); // break at "true", default value "false"
                $allow = CEC_Hook::executeWhileBreakCondition(
                    'Contenido.Frontend.CategoryAccess', $lang, $idcat, $auth->auth['uid']
                );
                $auth->login_if(!$allow);
            }
        }
        else
        {
            // CEC to check category access
            CEC_Hook::setBreakCondition(true, false); // break at "true", default value "false"
            $allow = CEC_Hook::executeWhileBreakCondition(
                'Contenido.Frontend.CategoryAccess', $lang, $idcat, $auth->auth['uid']
            );

            /*
                added 2008-11-18 Timo Trautmann
                in backendeditmode also check if logged in backenduser has permission to view preview of page
            */
            if ($allow == false && $contenido && $perm->have_perm_area_action_item("con_editcontent", "con_editart", $idcat)) {
                $allow = true;
            }

            if (!$allow)
            {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                header("Status: 404 Not Found");
                $_SERVER['REDIRECT_STATUS'] = 404;
                header($errsite);
				exit;
            }
        }
    }

    ##############################################
    # statistic
    ##############################################
    /* Sanity: If the statistic table doesn't contain an entry, create one */
    $sql = "SELECT idcatart FROM ".$cfg["tab"]["stat"]." WHERE idcatart = '".Contenido_Security::toInteger($idcatart)."' AND idlang='".Contenido_Security::toInteger($lang)."'";
    $db->query($sql);

    if ($db->next_record())
    {
        /* Update the statistics. */
        $sql = "UPDATE ".$cfg["tab"]["stat"]." SET visited = visited + 1 WHERE idcatart = '".Contenido_Security::toInteger($idcatart)."' AND idclient = '".Contenido_Security::toInteger($client)."'
                AND idlang = '".Contenido_Security::toInteger($lang)."'";
        $db->query($sql);
    }
    else
    {
        /* Insert new record */
        $next = $db->nextid($cfg["tab"]["stat"]);
        $sql = "INSERT INTO ".$cfg["tab"]["stat"]." (visited, idcatart, idlang, idstat, idclient) VALUES ('1', '".Contenido_Security::toInteger($idcatart)."', '".Contenido_Security::toInteger($lang)."',
                '".Contenido_Security::toInteger($next)."', '".Contenido_Security::toInteger($client)."')";
        $db->query($sql);
    }

    /*
     * Check if an article is start article of the category
     */
    if ($cfg["is_start_compatible"] == true)
    {
        $sql = "SELECT is_start FROM ".$cfg["tab"]["cat_art"]." WHERE idcatart='".Contenido_Security::toInteger($idcatart)."'";
        $db->query($sql);
        $db->next_record();
        $isstart = $db->f("is_start");
    }
    else
    {
        $sql = "SELECT startidartlang FROM ".$cfg["tab"]["cat_lang"]." WHERE idcat='".Contenido_Security::toInteger($idcat)."' AND idlang = '".Contenido_Security::toInteger($lang)."'";
        $db->query($sql);
        $db->next_record();
        $isstart = ($db->f("idartlang") == $idartlang);
    }

    ##############################################
    # time management
    ##############################################
    $sql = "SELECT timemgmt FROM ".$cfg["tab"]["art_lang"]." WHERE idart='".Contenido_Security::toInteger($idart)."' AND idlang = '".Contenido_Security::toInteger($lang)."'";
    $db->query($sql);
    $db->next_record();

    if (($db->f("timemgmt") == "1") && ($isstart != 1))
    {
        $sql = "SELECT online, redirect, redirect_url FROM ".$cfg["tab"]["art_lang"]." WHERE idart='".Contenido_Security::toInteger($idart)."' AND idlang = '".Contenido_Security::toInteger($lang)."'
                AND NOW() > datestart AND NOW() < dateend";
    }
    else
    {
        $sql = "SELECT online, redirect, redirect_url FROM ".$cfg["tab"]["art_lang"]." WHERE idart='".Contenido_Security::toInteger($idart)."' AND idlang = '".Contenido_Security::toInteger($lang)."'";
    }

    $db->query($sql);
    $db->next_record();

    $online = $db->f("online");
    $redirect = $db->f("redirect");
    $redirect_url = $db->f("redirect_url");

    @ eval ("\$"."redirect_url = \"$redirect_url\";"); // transform variables

    $insert_base = getEffectiveSetting('generator', 'basehref', "true");

    /*
     * generate base url
     */
    if ($insert_base == "true")
    {
        $is_XHTML = getEffectiveSetting('generator', 'xhtml', "false");

        $str_base_uri = $cfgClient[$client]["path"]["htmlpath"];

        // CEC for base href generation
        $str_base_uri = CEC_Hook::executeAndReturn('Contenido.Frontend.BaseHrefGeneration', $str_base_uri);

        if ($is_XHTML == "true") {
            $baseCode = '<base href="'.$str_base_uri.'" />';
        } else {
            $baseCode = '<base href="'.$str_base_uri.'">';
        }

        $code = str_ireplace_once("<head>", "<head>\n".$baseCode, $code);
    }

    /*
     * Handle online (offline) articles
     */
    if ($online)
    {
        if ($redirect == '1' && $redirect_url != '')
        {
            page_close();
            /*
             * Redirect to the URL defined in article properties
             */
            $oUrl = Contenido_Url::getInstance();
            if ($oUrl->isIdentifiableFrontContentUrl($redirect_url)) {
                // perform urlbuilding only for identified internal urls
                $aRedirUrl = explode('#', $redirect_url);
                $aUrl = $oUrl->parse($aRedirUrl[0]);
                if (!isset($aUrl['params']['lang'])) {
                    $aUrl['params']['lang'] = $lang;
                }
                $redirect_url = $oUrl->buildRedirect($aUrl['params']) . (((count($aRedirUrl == 2)) && (strlen($aRedirUrl[1]))) ? '#' . $aRedirUrl[1] : '');
            }
            header("Location: $redirect_url");
            exit;
        }
        else
        {
            if ($cfg["debug"]["codeoutput"])
            {
                echo "<textarea>".htmlspecialchars($code)."</textarea>";
            }

            /*
             * That's it! The code of an article will be evaluated.
             * The code of an article is basically a PHP script which is cached in the database.
             * Layout and Modules are merged depending on the Container definitions of the Template.
             */

            $aExclude = explode(',', getEffectiveSetting('frontend.no_outputbuffer', 'idart', ''));
            if (in_array(Contenido_Security::toInteger($idart), $aExclude)) {
                eval ("?>\n".$code."\n<?php\n");
            } else {
                // write html output into output buffer and assign it to an variable
                ob_start();
                eval ("?>\n".$code."\n<?php\n");
                $htmlCode = ob_get_contents();
                ob_end_clean();

                /*
                 * 2015-03-21 :: René Mansveld
                 * Last but not least, replace newsletter tags inf the article if it is a newsletter
                 * article and a recipient is set ($_GET['rcp']).
                 */
                if ($idcat == intval(getEffectiveSetting('newsletter', 'html_newsletter_idcat', -1))) {
                    if ((isset($_GET['nl'])) && (isset($_GET['rcp']))) {
                        # Regular newsletter job
                        $sql = 'SELECT idnewsjob, idnews
                                FROM ' . $cfg['tab']['news_jobs'] . '
                                WHERE (idnewsjob=' . Contenido_Security::toInteger($_GET['nl']) . ')';
                        $db->query($sql);
                        if ($db->next_record()) {
                            $idnews = $db->f('idnews');
                            $oNL = new Newsletter($idnews);
                            $oLanguage = new cApiLanguage($lang);
                            $sFormatDate = $oLanguage->getProperty("dateformat", "date");
                            $sFormatTime = $oLanguage->getProperty("dateformat", "time");
                            unset ($oLanguage);
                            $oNJ = new cNewsletterJob($db->f('idnewsjob'));
                            $dNewsDate = strtotime($oNJ->get("newsdate"));
                            $sPath = Contenido_Url::getInstance()->build(array('idcatart' => $iIDCatArt, 'client' => $client, 'lang' => $lang), true);
                            $sPath .= ((strpos($sPath, '?') === false) ? '?' : '&');
                            $oNL->_replaceTag($htmlCode, true, "number", $oNJ->get("rcpcount"));
                            $oNL->_replaceTag($htmlCode, true, "date", date($sFormatDate, $dNewsDate));
                            $oNL->_replaceTag($htmlCode, true, "time", date($sFormatTime, $dNewsDate));
                            $oNL->_replaceTag($htmlCode, true, "unsubscribe", $sPath."unsubscribe=".$_GET['rcp']);
                            $oNL->_replaceTag($htmlCode, true, "change", $sPath."change=".$_GET['rcp']);
                            $oNL->_replaceTag($htmlCode, true, "stop", $sPath."stop=".$_GET['rcp']);
                            $oNL->_replaceTag($htmlCode, true, "goon", $sPath."goon=".$_GET['rcp']);
                            $oLogs = new cNewsletterLogCollection();
                            $oLogs->setWhere('idnewsjob', $db->f('idnewsjob'));
                            $oLogs->setWhere('rcphash', $_GET['rcp']);
                            $oLogs->query();
                            if ($oLog = $oLogs->next()) {
                                $oNL->_replaceTag($htmlCode, true, "name", $oLog->get("rcpname"));
                                $oNL->_replaceTag($htmlCode, true, "email", $oLog->get("rcpemail"));
                            }
                            unset($oNL);
                            unset($oLogs);
                        }
                    } elseif (isset($_GET['rcp'])) {
                        # Welcome newsletter
                        $oNL = new Newsletter();
                        $oNL->_replaceTag($htmlCode, true, "unsubscribe", $sPath."unsubscribe=".$_GET['rcp']);
                        $oNL->_replaceTag($htmlCode, true, "change", $sPath."change=".$_GET['rcp']);
                        $oNL->_replaceTag($htmlCode, true, "stop", $sPath."stop=".$_GET['rcp']);
                        $oNL->_replaceTag($htmlCode, true, "goon", $sPath."goon=".$_GET['rcp']);
                        $oRcp = new Recipient($_GET['rcp']);
                        $oNL->_replaceTag($htmlCode, true, "name", $oRcp->get("name"));
                        $oNL->_replaceTag($htmlCode, true, "email", $oRcp->get("email"));
                        unset($oNL);
                        unset($oRcp);
                    }
                }

                // process CEC to do some preparations before output
                $htmlCode = CEC_Hook::executeAndReturn('Contenido.Frontend.HTMLCodeOutput', $htmlCode);

                // process CEC to handle the compression of the output
                $htmlCode = CEC_Hook::executeAndReturn('Contenido.Frontend.HTMLCodeCompression', $htmlCode);

                // print output
                echo $htmlCode;
            }

        }
    }
    else
    {
        # if user is in the backend display offline articles
        if ($contenido)
        {
            eval ("?>\n".$code."\n<?php\n");
        }
        else
        {
            if ($error == 1)
            {
                echo "Fatal error: Could not display error page. Error to display was: 'No drugcms session variable set. Probable error cause: Start article in this category is not set on-line.'";
            }
            else
            {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                header("Status: 404 Not Found");
                $_SERVER['REDIRECT_STATUS'] = 404;
                header($errsite);
				exit;
            }
        }
    }
}

/*
 * removed database roundtrip for checking
 * if cache is enabled
 * CON-115
 * 2008-06-25 Thorsten Granz
 */
// START: concache, murat purc
if ($cfg["cache"]["disable"] != '1') {
    $oCacheHandler->end();
    #echo $oCacheHandler->getInfo();
}
// END: concache

/*
 * configuration settings after the site is displayed.
 */
if (file_exists("config.after.php"))
{
    @ include ("config.after.php");
}

if (isset ($savedlang))
{
    $lang = $savedlang;
}

if (($debug) || (in_array(getEffectiveSetting('system', 'debug', 'false'), array('true', '1')))) {
    $oDbg = DebuggerFactory::getDebugger('visible_adv');
    $oDbg->showAll();
}

$db->disconnect();
page_close();
?>