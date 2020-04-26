<!-- BEGIN build -->
<table><tr><td>
<div class="build_container">
    <div class="profession"><a href="{gw_wiki_page_url}/{primary}"><img src="{gwbbcode_images_folder_url}/img_skin/{primary}.gif" border="0" /></a><a href="{gw_wiki_page_url}/{secondary}"><img src="{gwbbcode_images_folder_url}/img_skin/{secondary}.gif" border="0" /></a><span><a href="{gw_wiki_page_url}/{primary}"><b>{primary_display_name}</b></a> / <a href="{gw_wiki_page_url}/{secondary}"><b>{secondary_display_name}</b></a></span></div>
    <div class="attributes">
        {attributes}
    </div>
    <div class="description">
    {desc}
    </div>
    <div class="skills">
    {skills}
    </div>
    <div class="template">
        <div class="template_input"><input class="gws_template_input" type="text" value="{template_code}" readonly="readonly" />&nbsp;<a href="{pvx_wiki_page_url}/Special:DownloadTemplate?build={template_code}&amp;name={build_name}"><img src="{gwbbcode_images_folder_url}/img_skin/save.png" border="0" alt="save" /></a></div>
        <div class="template_name">Template code</div>
    </div>
</div>
</td></tr></table><span class="build_template_error">{template_error_msg}</span>
<!-- END build -->

<!-- BEGIN attribute -->
        <div>
            <div class="attribute_rank">{attribute_value}</div><div class="attribute_name"><a href="{gw_wiki_page_url}/{attribute_name}">{attribute_name}</a></div>
        </div>
<!-- END attribute -->

<!-- BEGIN icon -->
<div class="skill_box"><div class="skill_icon hovertooltip" data-tooltipid="load{load}"><div class="pvx_icon-{elite_or_normal}"><div class="pvx-type-{ty}"></div></div><a href="{gw_wiki_page_url}/{name_link}"><img src="{gwbbcode_images_folder_url}/img_skills/{id}.jpg" border="0" /></a></div><a href="{gw_wiki_page_url}/{name_link}">{name}</a></div>
<!-- END icon -->

<!-- BEGIN blank_icon -->
<img src="{gwbbcode_images_folder_url}/img_skills/{id}.jpg" style="vertical-align: middle;" />
<!-- END blank_icon -->

<!-- BEGIN noicon -->
<a href="{gw_wiki_page_url}/{name_link}" class="hovertooltip" data-tooltipid="load{load}" style="text-decoration: none">{name}</a>
<!-- END noicon -->

<!-- BEGIN noicon_showname -->
<a href="{gw_wiki_page_url}/{name_link}" class="hovertooltip" data-tooltipid="load{load}" style="text-decoration: none">{shown_name}</a>
<!-- END noicon_showname -->

<!-- BEGIN skill -->
<span id="load{load}" style="display: none;"><!--
    --><span class="pvx_overlib"><!--
        --><span class="pvx_campaign">{chapter}</span><!--
        --><span class="pvx_mastery">{profession}. {attr_html}</span><!--
        --><span class="pvx_description" style="background-image:url('{gwbbcode_images_folder_url}/img_skin/{prof_img}.png');"><!--
            --><span class="pvx_{elite_or_normal}">{name}</span><!--
            --><span class="pvx_type">{type}</span><!--
            --><span class="pvx_skill_info">{desc}{extra_desc}</span><!--
        --></span><!--
        --><span class="pvx_attrib_list"><!--
            --><span class="pvx_attrib_bg"><!--
                --><img src="{gwbbcode_images_folder_url}/img_thumb/{id}.jpg" height="40" width="40" border="0" /><span class="pvx_attributes">{required}</span><!--
            --></span><!--
        --></span><!--
    --></span><!--
--></span>
<!-- END skill -->

<!-- BEGIN requirement -->
<span class="pvx_{type}">{value}</span>
<!-- END requirement -->

<!-- BEGIN modified_requirement_value -->
<span class="expert">{modified_value}</span>
<!-- END modified_requirement_value -->

<!-- BEGIN tpl_extra_desc -->
<br/><span class="expert">{extra_desc}</span>
<!-- END tpl_extra_desc -->

<!-- BEGIN tpl_skill_attr -->
{attribute} {attr_value}
<!-- END tpl_skill_attr -->

<!-- BEGIN tpl_skill_no_attr -->
Unlinked
<!-- END tpl_skill_no_attr -->

<!-- BEGIN skillset -->
<div class="skillset">{skillset_value}</div>
<!-- END skillset -->
