<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title>Meva Kozmetika</title>
    <link>{{ $storefront }}</link>
    <description>Prirodna kozmetika, ručno rađena u Novom Pazaru.</description>
@foreach($items as $item)
    <item>
      <g:id>{{ $item['id'] }}</g:id>
      <g:title><![CDATA[{{ $item['title'] }}]]></g:title>
      <g:description><![CDATA[{{ $item['description'] }}]]></g:description>
      <g:link>{{ $item['link'] }}</g:link>
      <g:image_link>{{ $item['image'] }}</g:image_link>
      <g:availability>in stock</g:availability>
      <g:condition>new</g:condition>
      <g:price>{{ $item['price'] }}</g:price>
      <g:brand>Meva Kozmetika</g:brand>
      <g:identifier_exists>no</g:identifier_exists>
@if($item['category'])
      <g:product_type><![CDATA[{{ $item['category'] }}]]></g:product_type>
@endif
      <g:shipping>
        <g:country>RS</g:country>
        <g:price>0.00 RSD</g:price>
      </g:shipping>
    </item>
@endforeach
  </channel>
</rss>
