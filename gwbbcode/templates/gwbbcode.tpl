<!-- BEGIN build -->
<table><tr><td>
<div class="main">
    <div class="profession"><a href="https://wiki.guildwars.com/wiki/{primary}"><img src="/extensions/PvXCode/images/img_skin/{primary}.gif" border="0" /></a><a href="https://wiki.guildwars.com/wiki/{secondary}"><img src="/extensions/PvXCode/images/img_skin/{secondary}.gif" border="0" /></a><span><a href="https://wiki.guildwars.com/wiki/{primary}"><b>{primary}</b></a> / <a href="https://wiki.guildwars.com/wiki/{secondary}"><b>{secondary}</b></a></span></div>
    <div class="attribute_list">
        {attributes}
    </div><div style="clear: both;">
    {skills}
       <div class="template">
        <div class="template_name">Template code</div><div class="template_input"><input id="gws_template_input" type="text" value="{template_code}" readonly="readonly" />&nbsp;<a href="/Special:DownloadTemplate?build={template_code}&amp;name={build_name}"><img src="/extensions/PvXCode/images/img_skin/save.png" border="0" alt="save" /></a></div>
    </div>
</div>
</td></tr></table>
<!-- END build -->

<!-- BEGIN prof_icon -->

<!-- END prof_icon -->
<!-- BEGIN icon -->
<div class="skill_box"><div class="skill_icon hovertooltip" data-tooltipid="load{load}"><div class="pvx_icon-{elite_or_normal}"><div class="pvx-type-{ty}"></div></div><a href="https://wiki.guildwars.com/wiki/{name_link}"><img src="/extensions/PvXCode/images/img_skills/{id}.jpg" border="0" /></a></div><a href="https://wiki.guildwars.com/wiki/{name_link}">{name}</a></div>
<!-- END icon -->

<!-- BEGIN blank_icon -->
<img src="/extensions/PvXCode/images/img_skills/{id}.jpg" style="vertical-align: middle;" />
<!-- END blank_icon -->

<!-- BEGIN noicon -->
<a href="https://wiki.guildwars.com/wiki/{name_link}" class="hovertooltip" data-tooltipid="load{load}" style="text-decoration: none">{name}</a>
<!-- END noicon -->

<!-- BEGIN skill -->
<div id="load{load}" style="display: none;">
    <div class="pvx_overlib">
        <div class="pvx_campaign">{chapter}</div>
        <div class="pvx_mastery">{profession}. {attr_html}</div>
        <div class="pvx_description" style="background-image:url('/extensions/PvXCode/images/img_skin/{profession}.jpg');">
            <div class="pvx_{elite_or_normal}">{name}</div>
            <div class="pvx_type">{type}</div>
            <div class="pvx_skill_info">{desc}{extra_desc}</div>
        </div>
        <div class="pvx_attrib_list">
            <div class="pvx_attrib_bg">
                <img src="/extensions/PvXCode/images/img_thumb/{id}.jpg" height="40" width="40" border="0"><div id="pvx_attributes">{required}</div>
            </div>
        </div>
    </div>
</div>
<!-- END skill -->

<!-- BEGIN attribute -->
        <div class="attribute_rank">{attribute_value}</div><div class="attribute_name"><a href="https://wiki.guildwars.com/wiki/{attribute_name}">{attribute_name}</a></div>
<!-- END attribute -->

<!-- BEGIN requirement -->
<div id="pvx_{type}">{value}</div>
<!-- END requirement -->

<!-- BEGIN modified_requirement_value -->
<span class="expert">{modified_value}</span>
<!-- END modified_requirement_value -->

<!-- BEGIN tpl_desc -->
{desc}
<!-- END tpl_desc -->

<!-- BEGIN tpl_extra_desc -->
<br/><span class="expert">{extra_desc}</span>
<!-- END tpl_extra_desc -->

<!-- BEGIN tpl_skill_attr -->
{attribute} {attr_value}
<!-- END tpl_skill_attr -->

<!-- BEGIN tpl_skill_no_attr -->
Unlinked
<!-- END tpl_skill_no_attr -->
