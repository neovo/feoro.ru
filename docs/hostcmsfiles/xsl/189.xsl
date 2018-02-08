<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE xsl:stylesheet>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
xmlns:hostcms="http://www.hostcms.ru/"
exclude-result-prefixes="hostcms">
<xsl:output xmlns="http://www.w3.org/TR/xhtml1/strict" doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN" encoding="utf-8" indent="yes" method="html" omit-xml-declaration="no" version="1.0" media-type="text/xml"/>
<!-- СписокЭлементовИнфосистемы -->
<xsl:template match="/">
<xsl:apply-templates select="/informationsystem"/>
</xsl:template>
<xsl:template match="/informationsystem">
<!-- Получаем ID родительской группы и записываем в переменную $group -->
<xsl:variable name="group" select="group"/>
<div class="clients content clear">
<h6><xsl:value-of disable-output-escaping="yes" select="name"/></h6>
<div class="clcr clear">
<div class="cycle-slideshow" data-cycle-fx="scrollHorz" data-cycle-timeout="5000" data-cycle-pager=".pager" data-cycle-pause-on-hover="true" data-cycle-slides="> div" data-cycle-prev=".prev" data-cycle-next=".next">
<xsl:apply-templates select="informationsystem_item"/>
</div>
<div class="pager"></div>
<span class="prev" title="Назад">Назад</span>
<span class="next" title="Вперед">Вперед</span>
</div>
</div>
</xsl:template>

<!-- Шаблон вывода информационного элемента -->
<xsl:template match="informationsystem_item">
<div class="cl clear">
<div class="clinf">
<xsl:if test="image_small!=''">
<img src="{dir}{image_small}" alt="{name}" class="cli" />
</xsl:if>
<h5><xsl:value-of disable-output-escaping="yes" select="name"/></h5>
<p><xsl:value-of disable-output-escaping="yes" select="property_value[tag_name='base']/value"/></p>
</div>
	<div class="cld"><xsl:value-of disable-output-escaping="yes" select="description"/></div>
</div>
</xsl:template>
</xsl:stylesheet>