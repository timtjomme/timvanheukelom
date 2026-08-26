/*!
 * Bowser - a browser detector
 * https://github.com/ded/bowser
 * MIT License | (c) Dustin Diaz 2015
 */!function(name,definition){if(typeof module!='undefined'&&module.exports)module.exports=definition()
else if(typeof define=='function'&&define.amd)define(name,definition)
else this[name]=definition()}('bowser',function(){var t=true
function detect(ua){function getFirstMatch(regex){var match=ua.match(regex);return(match&&match.length>1&&match[1])||'';}
function getSecondMatch(regex){var match=ua.match(regex);return(match&&match.length>1&&match[2])||'';}
var iosdevice=getFirstMatch(/(ipod|iphone|ipad)/i).toLowerCase(),likeAndroid=/like android/i.test(ua),android=!likeAndroid&&/android/i.test(ua),nexusMobile=/nexus\s*[0-6]\s*/i.test(ua),nexusTablet=!nexusMobile&&/nexus\s*[0-9]+/i.test(ua),chromeos=/CrOS/.test(ua),silk=/silk/i.test(ua),sailfish=/sailfish/i.test(ua),tizen=/tizen/i.test(ua),webos=/(web|hpw)os/i.test(ua),windowsphone=/windows phone/i.test(ua),windows=!windowsphone&&/windows/i.test(ua),mac=!iosdevice&&!silk&&/macintosh/i.test(ua),linux=!android&&!sailfish&&!tizen&&!webos&&/linux/i.test(ua),edgeVersion=getFirstMatch(/edge\/(\d+(\.\d+)?)/i),versionIdentifier=getFirstMatch(/version\/(\d+(\.\d+)?)/i),tablet=/tablet/i.test(ua),mobile=!tablet&&/[^-]mobi/i.test(ua),xbox=/xbox/i.test(ua),result
if(/opera|opr|opios/i.test(ua)){result={name:'Opera',opera:t,version:versionIdentifier||getFirstMatch(/(?:opera|opr|opios)[\s\/](\d+(\.\d+)?)/i)}}
else if(/coast/i.test(ua)){result={name:'Opera Coast',coast:t,version:versionIdentifier||getFirstMatch(/(?:coast)[\s\/](\d+(\.\d+)?)/i)}}
else if(/yabrowser/i.test(ua)){result={name:'Yandex Browser',yandexbrowser:t,version:versionIdentifier||getFirstMatch(/(?:yabrowser)[\s\/](\d+(\.\d+)?)/i)}}
else if(/ucbrowser/i.test(ua)){result={name:'UC Browser',ucbrowser:t,version:getFirstMatch(/(?:ucbrowser)[\s\/](\d+(?:\.\d+)+)/i)}}
else if(/mxios/i.test(ua)){result={name:'Maxthon',maxthon:t,version:getFirstMatch(/(?:mxios)[\s\/](\d+(?:\.\d+)+)/i)}}
else if(/epiphany/i.test(ua)){result={name:'Epiphany',epiphany:t,version:getFirstMatch(/(?:epiphany)[\s\/](\d+(?:\.\d+)+)/i)}}
else if(/puffin/i.test(ua)){result={name:'Puffin',puffin:t,version:getFirstMatch(/(?:puffin)[\s\/](\d+(?:\.\d+)?)/i)}}
else if(/sleipnir/i.test(ua)){result={name:'Sleipnir',sleipnir:t,version:getFirstMatch(/(?:sleipnir)[\s\/](\d+(?:\.\d+)+)/i)}}
else if(/k-meleon/i.test(ua)){result={name:'K-Meleon',kMeleon:t,version:getFirstMatch(/(?:k-meleon)[\s\/](\d+(?:\.\d+)+)/i)}}
else if(windowsphone){result={name:'Windows Phone',windowsphone:t}
if(edgeVersion){result.msedge=t
result.version=edgeVersion}
else{result.msie=t
result.version=getFirstMatch(/iemobile\/(\d+(\.\d+)?)/i)}}
else if(/msie|trident/i.test(ua)){result={name:'Internet Explorer',msie:t,version:getFirstMatch(/(?:msie |rv:)(\d+(\.\d+)?)/i)}}else if(chromeos){result={name:'Chrome',chromeos:t,chromeBook:t,chrome:t,version:getFirstMatch(/(?:chrome|crios|crmo)\/(\d+(\.\d+)?)/i)}}else if(/chrome.+? edge/i.test(ua)){result={name:'Microsoft Edge',msedge:t,version:edgeVersion}}
else if(/vivaldi/i.test(ua)){result={name:'Vivaldi',vivaldi:t,version:getFirstMatch(/vivaldi\/(\d+(\.\d+)?)/i)||versionIdentifier}}
else if(sailfish){result={name:'Sailfish',sailfish:t,version:getFirstMatch(/sailfish\s?browser\/(\d+(\.\d+)?)/i)}}
else if(/seamonkey\//i.test(ua)){result={name:'SeaMonkey',seamonkey:t,version:getFirstMatch(/seamonkey\/(\d+(\.\d+)?)/i)}}
else if(/firefox|iceweasel|fxios/i.test(ua)){result={name:'Firefox',firefox:t,version:getFirstMatch(/(?:firefox|iceweasel|fxios)[ \/](\d+(\.\d+)?)/i)}
if(/\((mobile|tablet);[^\)]*rv:[\d\.]+\)/i.test(ua)){result.firefoxos=t}}
else if(silk){result={name:'Amazon Silk',silk:t,version:getFirstMatch(/silk\/(\d+(\.\d+)?)/i)}}
else if(/phantom/i.test(ua)){result={name:'PhantomJS',phantom:t,version:getFirstMatch(/phantomjs\/(\d+(\.\d+)?)/i)}}
else if(/slimerjs/i.test(ua)){result={name:'SlimerJS',slimer:t,version:getFirstMatch(/slimerjs\/(\d+(\.\d+)?)/i)}}
else if(/blackberry|\bbb\d+/i.test(ua)||/rim\stablet/i.test(ua)){result={name:'BlackBerry',blackberry:t,version:versionIdentifier||getFirstMatch(/blackberry[\d]+\/(\d+(\.\d+)?)/i)}}
else if(webos){result={name:'WebOS',webos:t,version:versionIdentifier||getFirstMatch(/w(?:eb)?osbrowser\/(\d+(\.\d+)?)/i)};if(/touchpad\//i.test(ua)){result.touchpad=t;}}
else if(/bada/i.test(ua)){result={name:'Bada',bada:t,version:getFirstMatch(/dolfin\/(\d+(\.\d+)?)/i)};}
else if(tizen){result={name:'Tizen',tizen:t,version:getFirstMatch(/(?:tizen\s?)?browser\/(\d+(\.\d+)?)/i)||versionIdentifier};}
else if(/qupzilla/i.test(ua)){result={name:'QupZilla',qupzilla:t,version:getFirstMatch(/(?:qupzilla)[\s\/](\d+(?:\.\d+)+)/i)||versionIdentifier}}
else if(/chromium/i.test(ua)){result={name:'Chromium',chromium:t,version:getFirstMatch(/(?:chromium)[\s\/](\d+(?:\.\d+)?)/i)||versionIdentifier}}
else if(/chrome|crios|crmo/i.test(ua)){result={name:'Chrome',chrome:t,version:getFirstMatch(/(?:chrome|crios|crmo)\/(\d+(\.\d+)?)/i)}}
else if(android){result={name:'Android',version:versionIdentifier}}
else if(/safari|applewebkit/i.test(ua)){result={name:'Safari',safari:t}
if(versionIdentifier){result.version=versionIdentifier}}
else if(iosdevice){result={name:iosdevice=='iphone'?'iPhone':iosdevice=='ipad'?'iPad':'iPod'}
if(versionIdentifier){result.version=versionIdentifier}}
else if(/googlebot/i.test(ua)){result={name:'Googlebot',googlebot:t,version:getFirstMatch(/googlebot\/(\d+(\.\d+))/i)||versionIdentifier}}
else{result={name:getFirstMatch(/^(.*)\/(.*) /),version:getSecondMatch(/^(.*)\/(.*) /)};}
if(!result.msedge&&/(apple)?webkit/i.test(ua)){if(/(apple)?webkit\/537\.36/i.test(ua)){result.name=result.name||"Blink"
result.blink=t}else{result.name=result.name||"Webkit"
result.webkit=t}
if(!result.version&&versionIdentifier){result.version=versionIdentifier}}else if(!result.opera&&/gecko\//i.test(ua)){result.name=result.name||"Gecko"
result.gecko=t
result.version=result.version||getFirstMatch(/gecko\/(\d+(\.\d+)?)/i)}
if(!result.msedge&&(android||result.silk)){result.android=t}else if(iosdevice){result[iosdevice]=t
result.ios=t}else if(mac){result.mac=t}else if(xbox){result.xbox=t}else if(windows){result.windows=t}else if(linux){result.linux=t}
var osVersion='';if(result.windowsphone){osVersion=getFirstMatch(/windows phone (?:os)?\s?(\d+(\.\d+)*)/i);}else if(iosdevice){osVersion=getFirstMatch(/os (\d+([_\s]\d+)*) like mac os x/i);osVersion=osVersion.replace(/[_\s]/g,'.');}else if(android){osVersion=getFirstMatch(/android[ \/-](\d+(\.\d+)*)/i);}else if(result.webos){osVersion=getFirstMatch(/(?:web|hpw)os\/(\d+(\.\d+)*)/i);}else if(result.blackberry){osVersion=getFirstMatch(/rim\stablet\sos\s(\d+(\.\d+)*)/i);}else if(result.bada){osVersion=getFirstMatch(/bada\/(\d+(\.\d+)*)/i);}else if(result.tizen){osVersion=getFirstMatch(/tizen[\/\s](\d+(\.\d+)*)/i);}
if(osVersion){result.osversion=osVersion;}
var osMajorVersion=osVersion.split('.')[0];if(tablet||nexusTablet||iosdevice=='ipad'||(android&&(osMajorVersion==3||(osMajorVersion>=4&&!mobile)))||result.silk){result.tablet=t}else if(mobile||iosdevice=='iphone'||iosdevice=='ipod'||android||nexusMobile||result.blackberry||result.webos||result.bada){result.mobile=t}
if(result.msedge||(result.msie&&result.version>=10)||(result.yandexbrowser&&result.version>=15)||(result.vivaldi&&result.version>=1.0)||(result.chrome&&result.version>=20)||(result.firefox&&result.version>=20.0)||(result.safari&&result.version>=6)||(result.opera&&result.version>=10.0)||(result.ios&&result.osversion&&result.osversion.split(".")[0]>=6)||(result.blackberry&&result.version>=10.1)||(result.chromium&&result.version>=20)){result.a=t;}
else if((result.msie&&result.version<10)||(result.chrome&&result.version<20)||(result.firefox&&result.version<20.0)||(result.safari&&result.version<6)||(result.opera&&result.version<10.0)||(result.ios&&result.osversion&&result.osversion.split(".")[0]<6)||(result.chromium&&result.version<20)){result.c=t}else result.x=t
return result}
var bowser=detect(typeof navigator!=='undefined'?navigator.userAgent:'')
bowser.test=function(browserList){for(var i=0;i<browserList.length;++i){var browserItem=browserList[i];if(typeof browserItem==='string'){if(browserItem in bowser){return true;}}}
return false;}
function getVersionPrecision(version){return version.split(".").length;}
function map(arr,iterator){var result=[],i;if(Array.prototype.map){return Array.prototype.map.call(arr,iterator);}
for(i=0;i<arr.length;i++){result.push(iterator(arr[i]));}
return result;}
function compareVersions(versions){var precision=Math.max(getVersionPrecision(versions[0]),getVersionPrecision(versions[1]));var chunks=map(versions,function(version){var delta=precision-getVersionPrecision(version);version=version+new Array(delta+1).join(".0");return map(version.split("."),function(chunk){return new Array(20-chunk.length).join("0")+chunk;}).reverse();});while(--precision>=0){if(chunks[0][precision]>chunks[1][precision]){return 1;}
else if(chunks[0][precision]===chunks[1][precision]){if(precision===0){return 0;}}
else{return-1;}}}
function isUnsupportedBrowser(minVersions,strictMode,ua){var _bowser=bowser;if(typeof strictMode==='string'){ua=strictMode;strictMode=void(0);}
if(strictMode===void(0)){strictMode=false;}
if(ua){_bowser=detect(ua);}
var version=""+_bowser.version;for(var browser in minVersions){if(minVersions.hasOwnProperty(browser)){if(_bowser[browser]){return compareVersions([version,minVersions[browser]])<0;}}}
return strictMode;}
function check(minVersions,strictMode,ua){return!isUnsupportedBrowser(minVersions,strictMode,ua);}
bowser.isUnsupportedBrowser=isUnsupportedBrowser;bowser.compareVersions=compareVersions;bowser.check=check;bowser._detect=detect;return bowser});(function($){UABBTrigger={triggerHook:function(hook,args){$('body').trigger('uabb-trigger.'+hook,args);},addHook:function(hook,callback){$('body').on('uabb-trigger.'+hook,callback);},removeHook:function(hook,callback){$('body').off('uabb-trigger.'+hook,callback);},};})(jQuery);jQuery(document).ready(function($){if(typeof bowser!=='undefined'&&bowser!==null){var uabb_browser=bowser.name,uabb_browser_v=bowser.version,uabb_browser_class=uabb_browser.replace(/\s+/g,'-').toLowerCase(),uabb_browser_v_class=uabb_browser_class+parseInt(uabb_browser_v);$('html').addClass(uabb_browser_class).addClass(uabb_browser_v_class);}
$('.uabb-row-separator').parents('html').css('overflow-x','hidden');});(function($){FLThemeBuilderHeaderLayout={win:null,body:null,header:null,overlay:false,hasAdminBar:false,stickyOn:'',breakpointWidth:0,init:function(){var editing=$('html.fl-builder-edit').length,header=$('.fl-builder-content[data-type=header]'),menuModule=header.find('.fl-module-menu'),breakpoint=null;if(!editing&&header.length){header.imagesLoaded($.proxy(function(){this.win=$(window);this.body=$('body');this.header=header.eq(0);this.overlay=!!Number(header.attr('data-overlay'));this.hasAdminBar=!!$('body.admin-bar').length;this.stickyOn=this.header.data('sticky-on');breakpoint=this.header.data('sticky-breakpoint');if(''==this.stickyOn){if(typeof FLBuilderLayoutConfig.breakpoints[breakpoint]!==undefined){this.breakpointWidth=FLBuilderLayoutConfig.breakpoints[breakpoint];}
else{this.breakpointWidth=FLBuilderLayoutConfig.breakpoints.medium;}}
if(Number(header.attr('data-sticky'))){this.header.data('original-top',this.header.offset().top);this.win.on('resize',$.throttle(500,$.proxy(this._initSticky,this)));this._initSticky();}},this));}},_initSticky:function(e){var header=$('.fl-builder-content[data-type=header]'),windowSize=this.win.width(),makeSticky=false;makeSticky=this._makeWindowSticky(windowSize);if(makeSticky||(this.breakpointWidth>0&&windowSize>=this.breakpointWidth)){this.win.on('scroll.fl-theme-builder-header-sticky',$.proxy(this._doSticky,this));if(e&&'resize'===e.type){if(this.header.hasClass('fl-theme-builder-header-sticky')){this._doSticky(e);}
this._adjustStickyHeaderWidth();}
if(Number(header.attr('data-shrink'))){this.header.data('original-height',this.header.outerHeight());this.win.on('resize',$.throttle(500,$.proxy(this._initShrink,this)));this._initShrink();}
this._initFlyoutMenuFix(e);}else{this.win.off('scroll.fl-theme-builder-header-sticky');this.win.off('resize.fl-theme-builder-header-sticky');this.header.removeClass('fl-theme-builder-header-sticky');this.header.removeAttr('style');this.header.parent().css('padding-top','0');}},_makeWindowSticky:function(windowSize){var makeSticky=false;switch(this.stickyOn){case'xl':makeSticky=windowSize>FLBuilderLayoutConfig.breakpoints['large'];break;case'':case'desktop':makeSticky=windowSize>=FLBuilderLayoutConfig.breakpoints['medium'];break;case'desktop-medium':makeSticky=windowSize>FLBuilderLayoutConfig.breakpoints['small'];break;case'large':makeSticky=windowSize>FLBuilderLayoutConfig.breakpoints['medium']&&windowSize<=FLBuilderLayoutConfig.breakpoints['large'];break;case'large-medium':makeSticky=windowSize>FLBuilderLayoutConfig.breakpoints['small']&&windowSize<=FLBuilderLayoutConfig.breakpoints['large'];break;case'medium':makeSticky=(windowSize<=FLBuilderLayoutConfig.breakpoints['medium']&&windowSize>FLBuilderLayoutConfig.breakpoints['small']);break;case'medium-mobile':makeSticky=(windowSize<=FLBuilderLayoutConfig.breakpoints['medium']);break;case'mobile':makeSticky=(windowSize<=FLBuilderLayoutConfig.breakpoints['small']);break;case'all':makeSticky=true;break;}
return makeSticky;},_doSticky:function(e){var winTop=Math.floor(this.win.scrollTop()),headerTop=Math.floor(this.header.data('original-top')),hasStickyClass=this.header.hasClass('fl-theme-builder-header-sticky'),hasScrolledClass=this.header.hasClass('fl-theme-builder-header-scrolled'),beforeHeader=this.header.prevAll('.fl-builder-content'),bodyTopPadding=parseInt(jQuery('body').css('padding-top')),winBarHeight=$('#wpadminbar').length?$('#wpadminbar').outerHeight():0,headerHeight=0;if(isNaN(bodyTopPadding)){bodyTopPadding=0;}
if(this.hasAdminBar&&this.win.width()>600){winTop+=Math.floor(winBarHeight);}
if(winTop>headerTop){if(!hasStickyClass){if(e&&('scroll'===e.type||'smartscroll'===e.type)){this.header.addClass('fl-theme-builder-header-sticky');if(this.overlay&&beforeHeader.length){this.header.css('top',winBarHeight);}}
if(!this.overlay){this._adjustHeaderHeight();}}}
else if(hasStickyClass){this.header.removeClass('fl-theme-builder-header-sticky');this.header.removeAttr('style');this.header.parent().css('padding-top','0');}
this._adjustStickyHeaderWidth();if(winTop>headerTop){if(!hasScrolledClass){this.header.addClass('fl-theme-builder-header-scrolled');}}else if(hasScrolledClass){this.header.removeClass('fl-theme-builder-header-scrolled');}
this._flyoutMenuFix(e);},_initFlyoutMenuFix:function(e){var header=this.header,menuModule=header.find('.fl-menu'),flyoutMenu=menuModule.find('.fl-menu-mobile-flyout'),isPushMenu=menuModule.hasClass('fl-menu-responsive-flyout-push')||menuModule.hasClass('fl-menu-responsive-flyout-push-opacity'),isSticky=header.hasClass('fl-theme-builder-header-sticky'),isOverlay=menuModule.hasClass('fl-menu-responsive-flyout-overlay'),flyoutPos=menuModule.hasClass('fl-flyout-right')?'right':'left',flyoutParent=header.parent().is('header')?header.parent().parent():header.parent();isFullWidth=this.win.width()===header.width(),flyoutLayout='',activePos=250,headerPos=0;if(!flyoutMenu.length){return;}
if(this.win.width()>header.parent().width()){headerPos=(this.win.width()-header.width())/ 2;}
if(isOverlay){activePos=headerPos;}
else if(isPushMenu){activePos=activePos+headerPos;}
flyoutMenu.data('activePos',activePos);if(isPushMenu){flyoutLayout='push-'+flyoutPos;}
else if(isOverlay){flyoutLayout='overlay-'+flyoutPos;}
if(isPushMenu&&!$('html').hasClass('fl-theme-builder-has-flyout-menu')){$('html').addClass('fl-theme-builder-has-flyout-menu');}
if(!flyoutParent.hasClass('fl-theme-builder-flyout-menu-'+flyoutLayout)){flyoutParent.addClass('fl-theme-builder-flyout-menu-'+flyoutLayout);}
if(!header.hasClass('fl-theme-builder-flyout-menu-overlay')&&isOverlay){header.addClass('fl-theme-builder-flyout-menu-overlay');}
if(!header.hasClass('fl-theme-builder-header-full-width')&&isFullWidth){header.addClass('fl-theme-builder-header-full-width');}
else if(!isFullWidth){header.removeClass('fl-theme-builder-header-full-width');}
menuModule.on('click','.fl-menu-mobile-toggle',$.proxy(function(event){if(menuModule.find('.fl-menu-mobile-toggle.fl-active').length){$('html').addClass('fl-theme-builder-flyout-menu-active');event.stopImmediatePropagation();}
else{$('html').removeClass('fl-theme-builder-flyout-menu-active');}
this._flyoutMenuFix(event);},this));},_flyoutMenuFix:function(e){var header=this.header,menuModule=header.find('.fl-menu'),flyoutMenu=menuModule.find('.fl-menu-mobile-flyout'),isPushMenu=menuModule.hasClass('fl-menu-responsive-flyout-push')||menuModule.hasClass('fl-menu-responsive-flyout-push-opacity'),flyoutPos=menuModule.hasClass('fl-flyout-right')?'right':'left',menuOpacity=menuModule.find('.fl-menu-mobile-opacity'),isScroll='undefined'!==typeof e&&'scroll'===e.handleObj.type,activePos='undefined'!==typeof flyoutMenu.data('activePos')?flyoutMenu.data('activePos'):0,headerPos=(this.win.width()-header.width())/ 2,inactivePos=headerPos>0?activePos+4:254;if(!flyoutMenu.length){return;}
if(this.overlay){return;}
if($('.fl-theme-builder-flyout-menu-active').length){if(isScroll&&!flyoutMenu.hasClass('fl-menu-disable-transition')){flyoutMenu.addClass('fl-menu-disable-transition');}
if(header.hasClass('fl-theme-builder-header-sticky')){if(!isScroll){setTimeout($.proxy(function(){flyoutMenu.css(flyoutPos,'-'+activePos+'px');},this),1);}
else{flyoutMenu.css(flyoutPos,'-'+activePos+'px');}}
else{flyoutMenu.css(flyoutPos,'0px');}}
else{if(flyoutMenu.hasClass('fl-menu-disable-transition')){flyoutMenu.removeClass('fl-menu-disable-transition');}
if(header.hasClass('fl-theme-builder-flyout-menu-overlay')&&headerPos>0&&headerPos<250){if(header.hasClass('fl-theme-builder-header-sticky')){inactivePos=headerPos+254;}
else{inactivePos=254;}}
if(e&&e.type==='resize'){inactivePos=headerPos+254;}
flyoutMenu.css(flyoutPos,'-'+inactivePos+'px');}
if(e&&menuModule.is('.fl-menu-responsive-flyout-overlay')&&$.infinitescroll){e.stopImmediatePropagation();}
if(menuOpacity.length){if(header.hasClass('fl-theme-builder-header-sticky')){if('0px'===menuOpacity.css('left')){menuOpacity.css('left','-'+headerPos+'px');}}
else{menuOpacity.css('left','');}}},_adjustStickyHeaderWidth:function(){if($('body').hasClass('fl-fixed-width')){var parentWidth=this.header.parent().width();if(this.win.width()>=992){this.header.css({'margin':'0 auto','max-width':parentWidth,});}
else{this.header.css({'margin':'','max-width':'',});}}},_adjustHeaderHeight:function(){var beforeHeader=this.header.prevAll('.fl-builder-content'),beforeHeaderHeight=0,beforeHeaderFix=0,headerHeight=Math.floor(this.header.outerHeight()),bodyTopPadding=parseInt($('body').css('padding-top')),wpAdminBarHeight=0,totalHeaderHeight=0;if(isNaN(bodyTopPadding)){bodyTopPadding=0;}
if(beforeHeader.length){$.each(beforeHeader,function(){beforeHeaderHeight+=Math.floor($(this).outerHeight());});beforeHeaderFix=2;}
if(this.hasAdminBar&&this.win.width()<=600){wpAdminBarHeight=Math.floor($('#wpadminbar').outerHeight());}
totalHeaderHeight=Math.floor(beforeHeaderHeight+headerHeight);if(headerHeight>0){var headerParent=this.header.parent(),headerParentTopPadding=0;if($(headerParent).is('body')){headerParentTopPadding=Math.floor(headerHeight-wpAdminBarHeight);}else{headerParentTopPadding=Math.floor(headerHeight-bodyTopPadding-wpAdminBarHeight);}
$(headerParent).css('padding-top',(headerParentTopPadding-beforeHeaderFix)+'px');this.header.css({'-webkit-transform':'translate(0px, -'+totalHeaderHeight+'px)','-ms-transform':'translate(0px, -'+totalHeaderHeight+'px)','transform':'translate(0px, -'+totalHeaderHeight+'px)'});}},_initShrink:function(e){if(this.win.width()>=this.breakpointWidth){this.win.on('scroll.fl-theme-builder-header-shrink',$.proxy(this._doShrink,this));this._setImageMaxHeight();if(this.win.scrollTop()>0){this._doShrink();}}else{this.header.parent().css('padding-top','0');this.win.off('scroll.fl-theme-builder-header-shrink');this._removeShrink();this._removeImageMaxHeight();}},_doShrink:function(e){var winTop=this.win.scrollTop(),headerTop=this.header.data('original-top'),headerHeight=this.header.data('original-height'),shrinkImageHeight=this.header.data('shrink-image-height'),windowSize=this.win.width(),makeSticky=this._makeWindowSticky(windowSize),hasClass=this.header.hasClass('fl-theme-builder-header-shrink');if(this.hasAdminBar){winTop+=32;}
if(makeSticky&&(winTop>headerTop+headerHeight)){if(!hasClass){this.header.addClass('fl-theme-builder-header-shrink');this.header.find('img').each(function(i){var image=$(this),maxMegaMenu=image.closest('.max-mega-menu').length,imageInLightbox=image.closest('.fl-button-lightbox-content').length,imageInNavMenu=image.closest('li.menu-item').length;if(!(imageInLightbox||imageInNavMenu||maxMegaMenu)){image.css('max-height',shrinkImageHeight);}});this.header.find('.fl-row-content-wrap').each(function(){var row=$(this);if(parseInt(row.css('padding-bottom'))>5){row.addClass('fl-theme-builder-header-shrink-row-bottom');}
if(parseInt(row.css('padding-top'))>5){row.addClass('fl-theme-builder-header-shrink-row-top');}});this.header.find('.fl-module-content').each(function(){var module=$(this);if(parseInt(module.css('margin-bottom'))>5){module.addClass('fl-theme-builder-header-shrink-module-bottom');}
if(parseInt(module.css('margin-top'))>5){module.addClass('fl-theme-builder-header-shrink-module-top');}});}}else if(hasClass){this.header.find('img').css('max-height','');this._removeShrink();}
if('undefined'===typeof(e)&&$('body').hasClass('fl-fixed-width')){if(!this.overlay){this._adjustHeaderHeight();}}},_removeShrink:function(){var rows=this.header.find('.fl-row-content-wrap'),modules=this.header.find('.fl-module-content');rows.removeClass('fl-theme-builder-header-shrink-row-bottom');rows.removeClass('fl-theme-builder-header-shrink-row-top');modules.removeClass('fl-theme-builder-header-shrink-module-bottom');modules.removeClass('fl-theme-builder-header-shrink-module-top');this.header.removeClass('fl-theme-builder-header-shrink');},_setImageMaxHeight:function(){var head=$('head'),stylesId='fl-header-styles-'+this.header.data('post-id'),styles='',images=this.header.find('.fl-module-content img');if($('#'+stylesId).length){return;}
images.each(function(i){var image=$(this),height=image.height(),node=image.closest('.fl-module').data('node'),className='fl-node-'+node+'-img-'+i,maxMegaMenu=image.closest('.max-mega-menu').length,imageInLightbox=image.closest('.fl-button-lightbox-content').length,imageInNavMenu=image.closest('li.menu-item').length;if(!(imageInLightbox||imageInNavMenu||maxMegaMenu)){image.addClass(className);styles+='.'+className+' { max-height: '+(height?height:image[0].height)+'px }';}});if(''!==styles){head.append('<style id="'+stylesId+'">'+styles+'</style>');}},_removeImageMaxHeight:function(){$('#fl-header-styles-'+this.header.data('post-id')).remove();},};$(function(){FLThemeBuilderHeaderLayout.init();});})(jQuery);