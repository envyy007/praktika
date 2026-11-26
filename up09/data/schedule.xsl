<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html" encoding="UTF-8" indent="yes" omit-xml-declaration="yes"/>
  <xsl:template match="/">
    <div class="table-wrap">
      <table>
        <caption>Ближайшие онлайн-занятия · <xsl:value-of select="count(schedule/lesson)"/> встреч</caption>
        <thead>
          <tr><th>Дата</th><th>День</th><th>Время</th><th>Тема</th><th>Повар</th><th>Мин</th><th>Мест</th><th>Стоимость</th></tr>
        </thead>
        <tbody>
          <xsl:for-each select="schedule/lesson">
            <xsl:sort select="@date" order="ascending"/>
            <xsl:sort select="@time" order="ascending"/>
            <tr>
              <td><xsl:value-of select="@date"/></td>
              <td><xsl:value-of select="@day"/></td>
              <td><xsl:value-of select="@time"/></td>
              <td><b><xsl:value-of select="@topic"/></b></td>
              <td><xsl:value-of select="@chef"/></td>
              <td><xsl:value-of select="@duration"/></td>
              <td><xsl:value-of select="@seats"/></td>
              <td>
                <xsl:choose>
                  <xsl:when test="@price &gt; 0"><xsl:value-of select="@price"/> ₽</xsl:when>
                  <xsl:otherwise>бесплатно</xsl:otherwise>
                </xsl:choose>
              </td>
            </tr>
          </xsl:for-each>
        </tbody>
      </table>
    </div>
  </xsl:template>
</xsl:stylesheet>