{**
 *
 * @author G. Giunta
 * @version $Id: admin_assets.tpl 53500 2010-01-26 09:55:57Z gg $
 * @copyright (C) G; giunta 2010
 *}
{* Details window. *}
<div class="context-block">

{* DESIGN: Header START *}<div class="box-header"><div class="box-tc"><div class="box-ml"><div class="box-mr"><div class="box-tl"><div class="box-tr">

<h2 class="context-title">eZSystemsMIB MIB Module</h2>

{* DESIGN: Subline *}<div class="header-subline"></div>

{* DESIGN: Header END *}</div></div></div></div></div></div>

{* DESIGN: Content START *}<div class="box-bc"><div class="box-ml"><div class="box-mr"><div class="box-bl"><div class="box-br"><div class="box-content">

<table class="list" cellspacing="0">
<tbody>
    <tr>
        <th>Object</th>
        <th>OID</th>
        <th>Syntax</th>
        <th>Description</th>
    </tr>
    {foreach $mib as $idx => $oid sequence array('bglight','bgdark') as $bgColor}
    <tr class="{$bgColor}">
        <td><a href={concat('/snmp/get/', $idx)|ezurl()}>eZSystems::{$oid.name|wash()}</a></td>
        <td>{$idx|wash()}</td>
        <td>{$oid.syntax|wash()}</td>
        <td>{$oid.description|wash()}</td>
    </tr>
    {/foreach}
</tbody>
</table>

{include name = Navigator
         uri = 'design:navigator/google.tpl'
         page_uri = concat('/snmp/mib/', $AssetClass)
         item_count = $mib|count()
         view_parameters = $view_parameters
         item_limit = $limit}

{* DESIGN: Content END *}</div></div></div></div></div></div>

{* DESIGN: /context-block *}</div>