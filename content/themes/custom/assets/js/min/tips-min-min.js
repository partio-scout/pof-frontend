window.Tips=function(t,e,$){var i={};return i.cache=function(){i.$section=$("#tips__section"),i.$tipsForm=$("#tips__form_data"),i.$loader=$("#tips__form_loader"),i.$container=$("#tips__form-container"),i.$counter=$("#tip-count"),i.$saveButton=$("#tips__save"),i.$filesContainer=$("#tips__image_input_container"),i.$addImage=$("#add_image_input"),i.$sorters=i.$section.find(".sort-filter"),i.$tips=i.$section.find(".tip"),i.pageID=i.$section.data("page"),i.fileIdx=0,i.loaded=!1,i.refresh()},i.refresh=function(){i.$errors=i.$section.find(".tips__error"),i.$generalErr=i.$section.find("#tips__general_errors")},i.init=function(){i.cache(),i.initListJS(),i.$sorters.on("click",function(t){i.stop(t),i.sortTips(t)}),i.$tipsForm.submit(i.saveTip)},i.initListJS=function(){var t={valueNames:["tip__date"],sortClass:"sort-filter"};i.tipsList=new List("tips__list_container",t),i.tipsList.on("updated",i.update)},i.update=function(){$("#tips__section").get(0).scrollIntoView()},i.saveTip=function(){i.$errors.hide(),i.$generalErr.html(""),i.$loader.show();var t=new FormData(i.$tipsForm[0]);return $.ajax({url:pof.tips_url,type:"POST",data:t,success:function(t){"string"==typeof t&&(t=JSON.parse(t)),"error"===t.status?i.handleError(t.message):i.handleSuccess(t.message),i.$loader.hide()},error:function(t){i.handleError("Vinkin lähettämisessä tapahtui virhe. Olkaa yhteydessä ylläpitoon: mikko.pori@partio.fi"),i.$loader.hide()},cache:!1,contentType:!1,processData:!1}),!1},i.sortTips=function(t){var e=t.target.dataset.sorter;i.tipsList.sort(e,{order:"desc"}),-1===t.target.className.indexOf("active")&&i.$sorters.toggleClass("active")},i.handleError=function(t){i.$generalErr.append("<p>"+t+"</p>").show()},i.handleSuccess=function(t){i.$container.html('<div data-alert class="alert-box info radius">'+t+"</div>")},i.stop=function(t){t.stopPropagation(),t.preventDefault()},i.forEach=function(t,e){for(var i=t.length-1;i>=0;i--)e(t[i])},$(e).ready(i.init),i}(window,document,jQuery);