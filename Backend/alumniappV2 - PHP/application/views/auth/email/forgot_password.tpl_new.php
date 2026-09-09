<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Email Template</title>
        <BASE HREF="http://www.titanicedge.com/pernodricard">
        <style media="screen" type="text/css">
        body{margin:0}article,aside,details,figcaption,figure,footer,header,hgroup,main,menu,nav,section,summary
        {display:block}audio,canvas,progress,video{display:inline-block;vertical-align:baseline}audio:not([controls])
        {display:none;height:0}[hidden],template{display:none}a{background-color:transparent}a:active,a:hover{outline:0}
        abbr[title]{border-bottom:1px dotted}b,strong{font-weight:700}dfn{font-style:italic}h1{margin:.67em 0;font-size:2em}
        mark{color:#000;background:#ff0}small{font-size:80%}sub,sup{position:relative;font-size:75%;line-height:0;
        vertical-align:baseline}sup{top:-.5em}sub{bottom:-.25em}img{border:0}svg:not(:root){overflow:hidden}figure{margin:1em 40px}
        hr{height:0;-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box}pre{overflow:auto}
        code,kbd,pre,samp{font-family:monospace,monospace;font-size:1em}*{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;
        box-sizing:border-box}:after,:before{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}html
        {font-size:10px;-webkit-tap-highlight-color:rgba(0,0,0,0)}body{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;
        font-size:14px;line-height:1.42857143;color:#333;background-color:#fff}button,input,select,textarea{font-family:inherit;
        font-size:inherit;line-height:inherit}a{color:#337ab7;text-decoration:none}a:focus,a:hover{color:#23527c;
        text-decoration:underline}a:focus{outline:thin dotted;outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}
        figure{margin:0}img{vertical-align:middle}.carousel-inner>.item>a>img,.carousel-inner>.item>img,.img-responsive,
        .thumbnail a>img,.thumbnail>img{display:block;max-width:100%;height:auto}.img-rounded{border-radius:6px}.img-thumbnail
        {display:inline-block;max-width:100%;height:auto;padding:4px;line-height:1.42857143;background-color:#fff;border:1px solid #ddd;
        border-radius:4px;-webkit-transition:all .2s ease-in-out;-o-transition:all .2s ease-in-out;transition:all .2s ease-in-out}
        .img-circle{border-radius:50%}hr{margin-top:20px;margin-bottom:20px;border:0;border-top:1px solid #eee}.sr-only{
        position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}.sr-only-focusable:active,.sr-only-focusable:
        focus{position:static;width:auto;height:auto;margin:0;overflow:visible;clip:auto}[role=button]{cursor:pointer}.h1,.h2,.h3,.h4,.h5,
        .h6,h1,h2,h3,h4,h5,h6{font-family:inherit;font-weight:500;line-height:1.1;color:inherit}.h1 .small,.h1 small,.h2 .small,.h2 small,
        .h3 .small,.h3 small,.h4 .small,.h4 small,.h5 .small,.h5 small,.h6 .small,.h6 small,h1 .small,h1 small,h2 .small,h2 small,h3 
        .small,h3 small,h4 .small,h4 small,h5 .small,h5 small,h6 .small,h6 small{font-weight:400;line-height:1;color:#777}.h1,.h2,.h3,h1,h2,h3
        {margin-top:20px;margin-bottom:10px}.h1 .small,.h1 small,.h2 .small,.h2 small,.h3 .small,.h3 small,h1 .small,h1 small,h2 .small,
        h2 small,h3 .small,h3 small{font-size:65%}.h4,.h5,.h6,h4,h5,h6{margin-top:10px;margin-bottom:10px}.h4 .small,.h4 small,.h5 .small,
        .h5 small,.h6 .small,.h6 small,h4 .small,h4 small,h5 .small,h5 small,h6 .small,h6 small{font-size:75%}.h1,h1{font-size:36px}.h2,h2
        {font-size:30px}.h3,h3{font-size:24px}.h4,h4{font-size:18px}.h5,h5{font-size:14px}.h6,h6{font-size:12px}p{margin:0 0 10px}
        .lead{margin-bottom:20px;font-size:16px;font-weight:300;line-height:1.4}@media (min-width:768px){.lead{font-size:21px}}.small,small
        {font-size:85%}.mark,mark{padding:.2em;background-color:#fcf8e3}.text-left{text-align:left}.text-right{text-align:right}
        .text-center{text-align:center}.text-justify{text-align:justify}.text-nowrap{white-space:nowrap}.text-lowercase
        {text-transform:lowercase}.text-uppercase{text-transform:uppercase}.text-capitalize{text-transform:capitalize}.text-muted{color:#777}
        .text-primary{color:#337ab7}a.text-primary:focus,a.text-primary:hover{color:#286090}.text-success{color:#3c763d}a.text-success:focus,
        a.text-success:hover{color:#2b542c}.text-info{color:#31708f}a.text-info:focus,a.text-info:hover{color:#245269}.text-warning
        {color:#8a6d3b}a.text-warning:focus,a.text-warning:hover{color:#66512c}.text-danger{color:#a94442}a.text-danger:focus,
        a.text-danger:hover{color:#843534}.bg-primary{color:#fff;background-color:#337ab7}a.bg-primary:focus,a.bg-primary:hover{
        background-color:#286090}.bg-success{background-color:#dff0d8}a.bg-success:focus,a.bg-success:hover{background-color:#c1e2b3}
        .bg-info{background-color:#d9edf7}a.bg-info:focus,a.bg-info:hover{background-color:#afd9ee}.bg-warning{background-color:#fcf8e3}
        a.bg-warning:focus,a.bg-warning:hover{background-color:#f7ecb5}.bg-danger{background-color:#f2dede}a.bg-danger:focus,a.bg-danger:hover
        {background-color:#e4b9b9}.page-header{padding-bottom:9px;margin:40px 0 20px;border-bottom:1px solid #eee}ol,ul{margin-top:0;
        margin-bottom:10px}ol ol,ol ul,ul ol,ul ul{margin-bottom:0}.list-unstyled{padding-left:0;list-style:none}.list-inline{padding-left:0;
        margin-left:-5px;list-style:none}.list-inline>li{display:inline-block;padding-right:5px;padding-left:5px}dl{margin-top:0;
        margin-bottom:20px}dd,dt{line-height:1.42857143}dt{font-weight:700}dd{margin-left:0}@media (min-width:768px){.dl-horizontal dt
        {float:left;width:160px;overflow:hidden;clear:left;text-align:right;text-overflow:ellipsis;white-space:nowrap}.dl-horizontal 
        dd{margin-left:180px}}abbr[data-original-title],abbr[title]{cursor:help;border-bottom:1px dotted #777}
        .initialism{font-size:90%;text-transform:uppercase} blockquote{padding:10px 20px;margin:0 0 20px;font-size:17.5px;border-left:5px solid #eee}blockquote ol:
        last-child,blockquote p:last-child,blockquote ul:last-child{margin-bottom:0}blockquote .small,blockquote footer,blockquote small
        {display:block;font-size:80%;line-height:1.42857143;color:#777} blockquote .small:before,blockquote footer:before,blockquote small:
        before{content:'\2014 \00A0'}.blockquote-reverse,blockquote.pull-right{padding-right:15px;padding-left:0;text-align:right;
        border-right:5px solid #eee;border-left:0}.blockquote-reverse .small:before,.blockquote-reverse footer:before,.blockquote-reverse 
        small:before,blockquote.pull-right .small:before,blockquote.pull-right footer:before,blockquote.pull-right small:before{content:''}
        .blockquote-reverse .small:after,.blockquote-reverse footer:after,.blockquote-reverse small:after,blockquote.pull-right .small:after,
        blockquote.pull-right footer:after,blockquote.pull-right small:after{content:'\00A0 \2014'}address{margin-bottom:20px;font-style:normal;
        line-height:1.42857143}code,kbd,pre,samp{font-family:Menlo,Monaco,Consolas,"Courier New",monospace}code{padding:2px 4px;font-size:90%;
        color:#c7254e;background-color:#f9f2f4;border-radius:4px}kbd{padding:2px 4px;font-size:90%;color:#fff;background-color:#333;
        border-radius:3px;-webkit-box-shadow:inset 0 -1px 0 rgba(0,0,0,.25);box-shadow:inset 0 -1px 0 rgba(0,0,0,.25)}kbd kbd{padding:0;
        font-size:100%;font-weight:700;-webkit-box-shadow:none;box-shadow:none}pre{display:block;padding:9.5px;margin:0 0 10px;font-size:13px;
        line-height:1.42857143;color:#333;word-break:break-all;word-wrap:break-word;background-color:#f5f5f5;border:1px solid #ccc;
        border-radius:4px}pre code{padding:0;font-size:inherit;color:inherit;white-space:pre-wrap;background-color:transparent;border-radius:0}
        .pre-scrollable{max-height:340px;overflow-y:scroll}.container{padding-right:15px;padding-left:15px;margin-right:auto;margin-left:auto}
        @media (min-width:768px){.container{width:750px}}@media (min-width:992px){.container{width:970px}}@media (min-width:1200px){.container
        {width:1170px}}.container-fluid{padding-right:15px;padding-left:15px;margin-right:auto;margin-left:auto}.row{margin-right:-15px;
        margin-left:-15px}.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,
        .col-lg-9,.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9,
        .col-sm-1,.col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9,.col-xs-1,
        .col-xs-10,.col-xs-11,.col-xs-12,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,.col-xs-6,.col-xs-7,.col-xs-8,.col-xs-9{position:relative;
        min-height:1px;padding-right:15px;padding-left:15px}.col-xs-1,.col-xs-10,.col-xs-11,.col-xs-12,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,
        .col-xs-6,.col-xs-7,.col-xs-8,.col-xs-9{float:left}.col-xs-12{width:100%}.col-xs-11{width:91.66666667%}.col-xs-10{width:83.33333333%}
        .col-xs-9{width:75%}.col-xs-8{width:66.66666667%}.col-xs-7{width:58.33333333%}.col-xs-6{width:50%}.col-xs-5{width:41.66666667%}
        .col-xs-4{width:33.33333333%}.col-xs-3{width:25%}.col-xs-2{width:16.66666667%}.col-xs-1{width:8.33333333%}.col-xs-pull-12{right:100%}
        .col-xs-pull-11{right:91.66666667%}.col-xs-pull-10{right:83.33333333%}.col-xs-pull-9{right:75%}.col-xs-pull-8{right:66.66666667%}
        .col-xs-pull-7{right:58.33333333%}.col-xs-pull-6{right:50%}.col-xs-pull-5{right:41.66666667%}.col-xs-pull-4{right:33.33333333%}
        .col-xs-pull-3{right:25%}.col-xs-pull-2{right:16.66666667%}.col-xs-pull-1{right:8.33333333%}.col-xs-pull-0{right:auto}.col-xs-push-12
        {left:100%}.col-xs-push-11{left:91.66666667%}.col-xs-push-10{left:83.33333333%}.col-xs-push-9{left:75%}.col-xs-push-8{left:66.66666667%}
        .col-xs-push-7{left:58.33333333%}.col-xs-push-6{left:50%}.col-xs-push-5{left:41.66666667%}.col-xs-push-4{left:33.33333333%}
        .col-xs-push-3{left:25%}.col-xs-push-2{left:16.66666667%}.col-xs-push-1{left:8.33333333%}.col-xs-push-0{left:auto}.col-xs-offset-12
        {margin-left:100%}.col-xs-offset-11{margin-left:91.66666667%}.col-xs-offset-10{margin-left:83.33333333%}.col-xs-offset-9{margin-left:75%}
        .col-xs-offset-8{margin-left:66.66666667%}.col-xs-offset-7{margin-left:58.33333333%}.col-xs-offset-6{margin-left:50%}.col-xs-offset-5
        {margin-left:41.66666667%}.col-xs-offset-4{margin-left:33.33333333%}.col-xs-offset-3{margin-left:25%}.col-xs-offset-2{
        margin-left:16.66666667%}.col-xs-offset-1{margin-left:8.33333333%}.col-xs-offset-0{margin-left:0}@media (min-width:768px){.col-sm-1,
        .col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9{float:left}.col-sm-12
        {width:100%}.col-sm-11{width:91.66666667%}.col-sm-10{width:83.33333333%}.col-sm-9{width:75%}.col-sm-8{width:66.66666667%}.col-sm-7{
        width:58.33333333%}.col-sm-6{width:50%}.col-sm-5{width:41.66666667%}.col-sm-4{width:33.33333333%}.col-sm-3{width:25%}.col-sm-2{
        width:16.66666667%}.col-sm-1{width:8.33333333%}.col-sm-pull-12{right:100%}.col-sm-pull-11{right:91.66666667%}.col-sm-pull-10{
        right:83.33333333%}.col-sm-pull-9{right:75%}.col-sm-pull-8{right:66.66666667%}.col-sm-pull-7{right:58.33333333%}.col-sm-pull-6{
        right:50%}.col-sm-pull-5{right:41.66666667%}.col-sm-pull-4{right:33.33333333%}.col-sm-pull-3{right:25%}.col-sm-pull-2{right:16.66666667%}
        .col-sm-pull-1{right:8.33333333%}.col-sm-pull-0{right:auto}.col-sm-push-12{left:100%}.col-sm-push-11{left:91.66666667%}.col-sm-push-10
        {left:83.33333333%}.col-sm-push-9{left:75%}.col-sm-push-8{left:66.66666667%}.col-sm-push-7{left:58.33333333%}.col-sm-push-6{left:50%}
        .col-sm-push-5{left:41.66666667%}.col-sm-push-4{left:33.33333333%}.col-sm-push-3{left:25%}.col-sm-push-2{left:16.66666667%}
        .col-sm-push-1{left:8.33333333%}.col-sm-push-0{left:auto}.col-sm-offset-12{margin-left:100%}.col-sm-offset-11{margin-left:91.66666667%}
        .col-sm-offset-10{margin-left:83.33333333%}.col-sm-offset-9{margin-left:75%}.col-sm-offset-8{margin-left:66.66666667%}.col-sm-offset-7
        {margin-left:58.33333333%}.col-sm-offset-6{margin-left:50%}.col-sm-offset-5{margin-left:41.66666667%}.col-sm-offset-4{
        margin-left:33.33333333%}.col-sm-offset-3{margin-left:25%}.col-sm-offset-2{margin-left:16.66666667%}.col-sm-offset-1{
        margin-left:8.33333333%}.col-sm-offset-0{margin-left:0}}@media (min-width:992px){.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,
        .col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9{float:left}.col-md-12{width:100%}.col-md-11{
        width:91.66666667%}.col-md-10{width:83.33333333%}.col-md-9{width:75%}.col-md-8{width:66.66666667%}.col-md-7{width:58.33333333%}
        .col-md-6{width:50%}.col-md-5{width:41.66666667%}.col-md-4{width:33.33333333%}.col-md-3{width:25%}.col-md-2{width:16.66666667%}
        .col-md-1{width:8.33333333%}.col-md-pull-12{right:100%}.col-md-pull-11{right:91.66666667%}.col-md-pull-10{right:83.33333333%}
        .col-md-pull-9{right:75%}.col-md-pull-8{right:66.66666667%}.col-md-pull-7{right:58.33333333%}.col-md-pull-6{right:50%}
        .col-md-pull-5{right:41.66666667%}.col-md-pull-4{right:33.33333333%}.col-md-pull-3{right:25%}.col-md-pull-2{right:16.66666667%}
        .col-md-pull-1{right:8.33333333%}.col-md-pull-0{right:auto}.col-md-push-12{left:100%}.col-md-push-11{left:91.66666667%}
        .col-md-push-10{left:83.33333333%}.col-md-push-9{left:75%}.col-md-push-8{left:66.66666667%}.col-md-push-7{left:58.33333333%}
        .col-md-push-6{left:50%}.col-md-push-5{left:41.66666667%}.col-md-push-4{left:33.33333333%}.col-md-push-3{left:25%}
        .col-md-push-2{left:16.66666667%}.col-md-push-1{left:8.33333333%}.col-md-push-0{left:auto}.col-md-offset-12{margin-left:100%}
        .col-md-offset-11{margin-left:91.66666667%}.col-md-offset-10{margin-left:83.33333333%}.col-md-offset-9{margin-left:75%}
        .col-md-offset-8{margin-left:66.66666667%}.col-md-offset-7{margin-left:58.33333333%}.col-md-offset-6{margin-left:50%}
        .col-md-offset-5{margin-left:41.66666667%}.col-md-offset-4{margin-left:33.33333333%}.col-md-offset-3{margin-left:25%}
        .col-md-offset-2{margin-left:16.66666667%}.col-md-offset-1{margin-left:8.33333333%}.col-md-offset-0{margin-left:0}}
        @media (min-width:1200px){.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,
        .col-lg-7,.col-lg-8,.col-lg-9{float:left}.col-lg-12{width:100%}.col-lg-11{width:91.66666667%}.col-lg-10{width:83.33333333%}
        .col-lg-9{width:75%}.col-lg-8{width:66.66666667%}.col-lg-7{width:58.33333333%}.col-lg-6{width:50%}.col-lg-5{width:41.66666667%}
        .col-lg-4{width:33.33333333%}.col-lg-3{width:25%}.col-lg-2{width:16.66666667%}.col-lg-1{width:8.33333333%}.col-lg-pull-12{
        right:100%}.col-lg-pull-11{right:91.66666667%}.col-lg-pull-10{right:83.33333333%}.col-lg-pull-9{right:75%}.col-lg-pull-8
        {right:66.66666667%}.col-lg-pull-7{right:58.33333333%}.col-lg-pull-6{right:50%}.col-lg-pull-5{right:41.66666667%}.col-lg-pull-4
        {right:33.33333333%}.col-lg-pull-3{right:25%}.col-lg-pull-2{right:16.66666667%}.col-lg-pull-1{right:8.33333333%}.col-lg-pull-0
        {right:auto}.col-lg-push-12{left:100%}.col-lg-push-11{left:91.66666667%}.col-lg-push-10{left:83.33333333%}.col-lg-push-9{left:75%}
        .col-lg-push-8{left:66.66666667%}.col-lg-push-7{left:58.33333333%}.col-lg-push-6{left:50%}.col-lg-push-5{left:41.66666667%}
        .col-lg-push-4{left:33.33333333%}.col-lg-push-3{left:25%}.col-lg-push-2{left:16.66666667%}.col-lg-push-1{left:8.33333333%}
        .col-lg-push-0{left:auto}.col-lg-offset-12{margin-left:100%}.col-lg-offset-11{margin-left:91.66666667%}
        .col-lg-offset-10{margin-left:83.33333333%}.col-lg-offset-9{margin-left:75%}.col-lg-offset-8{margin-left:66.66666667%}
        .col-lg-offset-7{margin-left:58.33333333%}.col-lg-offset-6{margin-left:50%}.col-lg-offset-5{margin-left:41.66666667%}
        .col-lg-offset-4{margin-left:33.33333333%}.col-lg-offset-3{margin-left:25%}.col-lg-offset-2{margin-left:16.66666667%}
        .col-lg-offset-1{margin-left:8.33333333%}.col-lg-offset-0{margin-left:0}}@media (max-width:767px){.visible-xs
        {display:block!important}table.visible-xs{display:table!important}tr.visible-xs{display:table-row!important}td.visible-xs,
        th.visible-xs{display:table-cell!important}}@media (max-width:767px){.visible-xs-block{display:block!important}}@media 
        (max-width:767px){.visible-xs-inline{display:inline!important}}@media (max-width:767px){.visible-xs-inline-block{
        display:inline-block!important}}@media (min-width:768px) and (max-width:991px){.visible-sm{display:block!important}
        table.visible-sm{display:table!important}tr.visible-sm{display:table-row!important}td.visible-sm,th.visible-sm{
        display:table-cell!important}}@media (min-width:768px) and (max-width:991px){.visible-sm-block{display:block!important}}
        @media (min-width:768px) and (max-width:991px){.visible-sm-inline{display:inline!important}}@media (min-width:768px) and 
        (max-width:991px){.visible-sm-inline-block{display:inline-block!important}}@media (min-width:992px) and (max-width:1199px){
        .visible-md{display:block!important}table.visible-md{display:table!important}tr.visible-md{display:table-row!important}
        td.visible-md,th.visible-md{display:table-cell!important}}@media (min-width:992px) and (max-width:1199px){.visible-md-block
        {display:block!important}}@media (min-width:992px) and (max-width:1199px){.visible-md-inline{display:inline!important}}
        @media (min-width:992px) and (max-width:1199px){.visible-md-inline-block{display:inline-block!important}}@media (
        min-width:1200px){.visible-lg{display:block!important}table.visible-lg{display:table!important}tr.visible-lg{
        display:table-row!important}td.visible-lg,th.visible-lg{display:table-cell!important}}@media (min-width:1200px){
        .visible-lg-block{display:block!important}}@media (min-width:1200px){.visible-lg-inline{display:inline!important}}@media (
        min-width:1200px){.visible-lg-inline-block{display:inline-block!important}}@media (max-width:767px){.hidden-xs{
        display:none!important}}@media (min-width:768px) and (max-width:991px){.hidden-sm{display:none!important}}@media (
        min-width:992px) and (max-width:1199px){.hidden-md{display:none!important}}@media (min-width:1200px){.hidden-lg{
        display:none!important}}.visible-print{display:none!important}@media print{.visible-print{display:block!important}
        table.visible-print{display:table!important}tr.visible-print{display:table-row!important}td.visible-print,th.visible-print
        {display:table-cell!important}}.visible-print-block{display:none!important}@media print{.visible-print-block{
        display:block!important}}.visible-print-inline{display:none!important}@media print{.visible-print-inline{
        display:inline!important}}.visible-print-inline-block{display:none!important}@media print{.visible-print-inline-block
        {display:inline-block!important}}@media print{.hidden-print{display:none!important}}
        body{-webkit-font-smoothing:antialiased;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;color:#424242;font-weight:400;
        font-size:13px;line-height:24px;background-color:#fff;font-family:verdana,Helvetica,verdana,Arial,sans-serif}*,*:before,
        *:after{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}:focus{outline:0 !important}iframe
        {border:none;width:100%}::selection{background:#fefac7;color:#555555}::-moz-selection{background:#fefac7;color:#555555}input,
        button,select,textarea,label{font-family:verdana,Helvetica,verdana,Arial,sans-serif;font-size:14px;font-weight:400}
        hr{background-image:-webkit-linear-gradient(left, transparent, rgba(0,0,0,0.2), transparent);background-image:linear-gradient
        (to right, transparent, rgba(0,0,0,0.2), transparent);border:0;height:1px;margin:22px 0 22px 0}.badge{font-weight:normal;
        margin-left:5px;-webkit-border-radius:0px;-moz-border-radius:0px;-ms-border-radius:0px;border-radius:0px;background-color:#39c;
        padding:3px 6px}a{color:#333;-moz-transition:all 200ms ease-in;-o-transition:all 200ms ease-in;-webkit-transition:all 200ms ease-in;
        transition:all 200ms ease-in}a:hover,a:focus{color:#39c;text-decoration:none}.colored-text{color:#39c}h1,h2,h3,h4,h5,h6{
        color:#060606;font-weight:700;margin:0;font-family:verdana,Helvetica,verdana,Arial,sans-serif}h1{font-size:3.2em;
        line-height:44px;margin:0 0 44px 0}h2{font-size:2.6em;font-weight:700;line-height:42px;margin:0 0 32px 0}h3{font-size:1.8em;
        font-weight:500;letter-spacing:normal;line-height:24px;margin-bottom:15px}h3.heading{color:#000;font-size:20px;font-weight:600;
        font-style:normal;margin:0 0 20px;padding-bottom:10px;position:relative;text-transform:capitalize;overflow:hidden;
        vertical-align:middle;font-family:verdana,Helvetica,verdana,Arial,sans-serif}h3.heading:after{position:relative;left:18px;
        content:"";display:inline-block;width:200%;vertical-align:middle;height:.50em;margin:0 -200% 0 0;border-top:1px solid #eee;
        border-bottom:1px solid #eee}h4{font-size:1.4em;font-weight:700;letter-spacing:normal;line-height:27px;margin:0 0 14px 0}h5{
        font-size:1em;font-weight:700;letter-spacing:normal;line-height:18px;margin:0 0 14px 0}h6{color:#333;font-size:1em;
        font-weight:700;letter-spacing:normal;line-height:18px;margin:0 0 14px 0}p{color:#555;line-height:24px;margin:0 0 20px}
        p.sub-text{font-style:normal;font-size:16px;line-height:29px;font-weight:300;color:#555}p.lead{font-size:16px;font-weight:400}
        pre:after,pre:before{clear:both;display:table;content:""}.center-heading{text-align:center;margin-bottom:40px}
        .center-heading h2{margin-bottom:0;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:#333;font-size:25px}
        .center-heading p{font-size:20px;line-height:35px}.center-heading h2 strong{font-weight:700}.center-line{display:inline-block;
        width:70px;height:1px;border-top:1px solid #bbb;margin:auto}.center-heading p{margin-top:10px}.overflow-hidden{overflow:hidden}
        .divide0{width:100%;height:0px}.divide2{width:100%;height:2px}.divide3{width:100%;height:3px}.divide5{width:100%;height:5px}
        .divide10{width:100%;height:10px}.divide15{width:100%;height:15px}.divide20{width:100%;height:20px}.divide25{width:100%;
        height:25px}.divide30{width:100%;height:30px}.divide35{width:100%;height:35px}.divide40{width:100%;height:40px}
        .divide45{width:100%;height:40px}.divide50{width:100%;height:50px}.divide55{width:100%;height:55px}.divide60{width:100%;
        height:60px}.divide65{width:100%;height:65px}.divide70{width:100%;height:70px}.divide75{width:100%;height:75px}.divide80{width:100%;
        height:80px}.divide85{width:100%;height:85px}.margin10{margin-bottom:10px}.margin20{margin-bottom:20px}.margin30{
        margin-bottom:30px}.margin40{margin-bottom:40px}.no-padding{padding:0px}.no-padding.gray{background-color:#f5f5f5}
        .no-padding-inner h3{text-transform:uppercase;font-weight:900;margin-bottom:40px}.no-padding-inner{padding:60px}
        @media (max-width: 568px){.no-padding-inner{padding:15px}}.margin-0{margin:0px !important}.btn{font-weight:400;
        letter-spacing:0px;-webkit-border-radius:2px;-moz-border-radius:2px;-ms-border-radius:2px;border-radius:2px;font-size:14px;
        text-transform:uppercase}.btn-default:hover,.btn-default:focus{background-color:#333;border-color:#333;color:#fff}.btn-lg
        {padding:14px 28px 13px 28px;font-size:13px;font-weight:400}.btn:focus,.btn:hover{outline:0;color:#fff}
        .btn-xs{font-size:12px}.btn-theme-bg{border-color:#39c;color:#fff;background-color:#39c}.btn i{margin-right:10px}.btn-theme-bg:hover{background-color:#333;border-color:#333}.btn-theme-dark{background-color:#333;color:#fff}.btn-theme-dark:hover{
        color:#fff;background-color:#39c}.border-black{border:1px solid #333;color:#000;background-color:transparent}
        .border-black:hover{background-color:#000;border-color:#000;color:#fff}.border-white{border:1px solid #fff;color:#fff}
        .border-white:hover{background-color:#fff;border-color:#fff;color:#000}.border-theme{border:1px solid #39c;color:#39c}
        .border-theme:hover{background-color:#39c;border-color:#39c;color:#fff}.btn-3d{border-bottom:3px solid rgba(0,0,0,0.3)}
        .btn-ico i{margin-left:5px}.navbar-default{border:none;-webkit-border-radius:0px;-moz-border-radius:0px;
        -ms-border-radius:0px;border-radius:0px;margin-bottom:0;width:100%;min-height:70px;padding:10px 0;
        -webkit-box-shadow:rgba(0,0,0,0.11765) 0px 1px 3px;-moz-box-shadow:rgba(0,0,0,0.11765) 0px 1px 3px;
        -ms-box-shadow:rgba(0,0,0,0.11765) 0px 1px 3px;box-shadow:rgba(0,0,0,0.11765) 0px 1px 3px;background-color:rgba(255,255,255,0.9);
        clear:both;-moz-transition:all 0.4s ease-in-out;-o-transition:all 0.4s ease-in-out;-webkit-transition:all 0.4s ease-in-out;
        transition:all 0.4s ease-in-out}.navbar-default .navbar-nav>.active>a,.navbar-default .navbar-nav>.active>a:hover,
        .navbar-default .navbar-nav>.active>a:focus{color:#39c;background-color:transparent}.navbar-default .navbar-nav>.open>a,
        .navbar-default .navbar-nav>.open>a:hover,.navbar-default .navbar-nav>.open>a:focus{color:#39c;background-color:transparent}
        .navbar-brand{font-weight:700;color:#000 !important;font-size:30px;line-height:20px;font-style:normal;text-transform:uppercase}
        .navbar-default .navbar-nav>li>a{color:#687074;font-size:13px;font-weight:400;text-transform:uppercase;
        font-family:verdana,Helvetica,verdana,Arial,sans-serif}.navbar-default .navbar-nav>li>a:hover{color:#39c}
        .navbar-brand img{width:auto;height:auto}.navbar .dropdown-menu{padding:0px;margin:0;min-width:150px;background-color:#fff;
        border:0;-webkit-border-radius:0px;-moz-border-radius:0px;-ms-border-radius:0px;border-radius:0px;-webkit-box-shadow:none;
        -moz-box-shadow:none;-ms-box-shadow:none;box-shadow:none;border:1px solid #eee}.search-dropdown{min-width:244px !important}
        .navbar .dropdown-menu li a{color:#777;font-size:14px;font-weight:400;border-bottom:1px solid #f5f5f5;padding:6px 16px;
        line-height:1.42857143;text-transform:capitalize}.dropdown-menu .label{margin-top:6px}.navbar .dropdown-menu li a:hover
        {background-color:#f5f5ff;color:#209b60}.top-bar-dark{background-color:#333}.top-bar-light{background-color:#f3f3f3}
        .top-bar-light .top-dark-right li{border-color:#ddd}.top-bar-light .top-dark-right li a:hover{color:#39c}.top-bar-socials
        {line-height:18px;padding-top:5px}.top-bar-socials:after{display:table;clear:both;content:""}.top-bar-socials a{margin:0px 3px}
        .top-dark-right{margin:0px;padding:0px}.top-dark-right li{line-height:40px;border-left:1px solid #444;padding:0px 10px}
        .top-dark-right li,.top-dark-right li a{color:#bbb;font-size:12px}.top-dark-right li i{margin-right:5px}
        .top-dark-right li a:hover{color:#fff}.topbar-icons{display:block}.topbar-icons i{margin:0px !important;display:block}
        .search{display:none;position:absolute;left:0;right:15px;top:0;height:100%;z-index:99999}.search .form-control{height:100%;
        position:absolute;top:0;width:99%;right:0;border:0px;background-color:#fff;-webkit-box-shadow:none;-moz-box-shadow:none;
        -ms-box-shadow:none;box-shadow:none;-webkit-border-radius:0px;-moz-border-radius:0px;-ms-border-radius:0px;border-radius:0px}
        .search-close{position:absolute;right:9px;top:5px;cursor:pointer}.yamm-content{padding:25px;box-sizing:border-box;
        background-color:#fff}.dropdown-menu .divider{background-color:#8fd0e0}.yamm-content h3.heading{border-bottom:none;
        margin:0 0 5px;color:#000;font-size:13px;font-weight:400;text-transform:uppercase}.yamm-content h3:before{content:"";
        display:none}.yamm-content .mega-vertical-nav{margin-bottom:30px}.yamm-content .mega-vertical-nav li a{padding:8px 0px;
        -moz-transition:all 0.3s ease-in;-o-transition:all 0.3s ease-in;-webkit-transition:all 0.3s ease-in;transition:all 0.3s ease-in;
        color:#888;border-bottom:1px solid #fbfbfb;font-size:14px;font-weight:400;text-transform:capitalize}.nav.mega-vertical-nav li a:hover
        {background-color:transparent;color:#39c}.nav.mega-vertical-nav li a i{margin-right:10px}.mega-contact p{margin:0}
        .mega-contact i{color:#39c;margin-right:5px}.dropdown-form{padding:10px 27px;min-width:213px}.dropdown-form .form-control
        {height:34px}.top-bar form{position:relative;margin:0px;padding:0px}.top-bar button{border:0px;background-color:transparent;
        position:absolute;top:9px;right:13px;margin:0px;padding:0px;width:auto;height:auto;line-height:15px}.top-bar form 
        .form-control{-webkit-border-radius:20px;-moz-border-radius:20px;-ms-border-radius:20px;border-radius:20px;margin-top:6px;
        width:150px;-moz-transition:all 0.3s ease-in-out;-o-transition:all 0.3s ease-in-out;-webkit-transition:all 0.3s ease-in-out;
        transition:all 0.3s ease-in-out}.top-bar .form-group{margin:0px}.top-bar .form-control:focus{width:200px}.navbar-inverse
        {border:none;-webkit-border-radius:0px;-moz-border-radius:0px;-ms-border-radius:0px;border-radius:0px;margin-bottom:0;
        width:100%;min-height:70px;padding:10px 0;-webkit-box-shadow:-1px 1px 1px rgba(0,0,0,0.1);-moz-box-shadow:-1px 1px 1px rgba(0,0,0,0.1);
        -ms-box-shadow:-1px 1px 1px rgba(0,0,0,0.1);box-shadow:-1px 1px 1px rgba(0,0,0,0.1);background-color:#333;
        -moz-transition:all 0.4s ease-in-out;-o-transition:all 0.4s ease-in-out;-webkit-transition:all 0.4s ease-in-out;
        transition:all 0.4s ease-in-out}.navbar-inverse .navbar-nav>.active>a,.navbar-inverse .navbar-nav>.active>a:hover,.navbar-inverse 
        .navbar-nav>.active>a:focus{color:#39c;background-color:transparent}.navbar-inverse .navbar-nav>.open>a,.navbar-inverse 
        .navbar-nav>.open>a:hover,.navbar-inverse .navbar-nav>.open>a:focus{color:#39c;background-color:transparent}.navbar-inverse 
        .navbar-brand{font-weight:700;color:#fff !important;font-size:30px;line-height:20px;font-style:normal;text-transform:uppercase}
        .navbar-inverse .navbar-nav>li>a{color:#fff;font-size:13px;text-transform:uppercase}.navbar-inverse .navbar-nav>li>a:hover{color:#39c}
        #header-top.dark-header-top{background-color:#111;border-bottom-color:#222}#header-top.dark-header-top .top-bar a i{color:#fff}
        #header-top.dark-header-top .top-bar ul li{color:#fff}.navbar-inverse.transparent-header{background-color:transparent;
        -webkit-box-shadow:none;-moz-box-shadow:none;-ms-box-shadow:none;box-shadow:none;padding:10px 0px;
        min-height:50px}@media (max-width: 767px){.navbar-inverse.transparent-header{background-color:#111 !important}}
        .header-center{border-top:1px solid #ddd;border-bottom:1px solid #ddd;background-color:#f5f5f5;padding:25px 0}
        .header-center a{font-size:30px;text-transform:uppercase;color:#000;font-weight:700}.header-center span{display:block}
        .navbar-default.menu-header-center{padding:0px;min-height:50px}.navbar-default.menu-header-center .navbar-nav>li{
        border-left:1px solid #ddd}#boxed{background-color:#E6EAFD}@media (min-width: 1200px){.boxed-wrapper{margin:0  
        auto;width:1170px;background-color:white;-webkit-box-shadow:0px 0px 25px rgba(0,0,0,0.16);
        -moz-box-shadow:0px 0px 25px rgba(0,0,0,0.16);-ms-box-shadow:0px 0px 25px rgba(0,0,0,0.16);box-shadow:0px 0px 25px rgba(0,0,0,0.16)}}
        .boxed-wrapper .tp-banner-container{margin:0px auto}.boxed-wrapper .navbar-default{left:auto;width:auto;right:auto}.side-panel-page{
        overflow-x:hidden}.side-panel{padding:15px 0;background-color:#fff}.side-panel .offcanvas-toggle-right.navbar-toggle{
        display:block;padding:0px;margin:0px;font-size:18px}.offcanvas-side-content .logo-side-nav{padding:25px 15px;text-align:center}
        .offcanvas-side-content .navmenu{width:220px;padding:0}.offcanvas-side-content li.nav-header{padding:15px}
        .offcanvas-side-content .profile-element img{display:block;margin:0 auto}.offcanvas-side-content .profile-element 
        .dropdown-menu{border:0px;-webkit-border-radius:0px;-moz-border-radius:0px;-ms-border-radius:0px;border-radius:0px;
        -webkit-box-shadow:none;-moz-box-shadow:none;-ms-box-shadow:none;box-shadow:none;padding:0px}.offcanvas-side-content 
        .profile-element .dropdown-menu>li:last-child a{border-bottom:0px}.offcanvas-side-content .profile-element 
        .dropdown-menu>li>a{padding:8px 15px;border-bottom:1px solid #f5f5f5}.offcanvas-side-content .profile-element 
        .dropdown-menu>li>a i{margin-right:5px}.metismenu>li>a{border-bottom:1px solid #eee;padding:7px 15px}.metismenu 
        .arrow{float:right;position:absolute;right:10px;top:14px;display:inline-block;font:normal normal normal 14px/1 FontAwesome;
        font-size:inherit;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;transform:translate(0, 0)}.metismenu 
        .arrow:before{content:"\f196"}.metismenu .active>a>.arrow:before{content:"\f147"}.metismenu .nav-second-level>li a{padding:7px 15px;padding-left:45px}
        .metismenu .nav-third-level>li a{padding:7px 15px;padding-left:55px}@media (max-width: 991px){.navbar-default 
        .nav>li>a{padding:10px 12px;padding-top:15px;padding-bottom:15px;font-size:12px}.navbar-default .nav>li>a i{margin-right:3px}
        .navbar-inverse .nav>li>a{padding:10px 11px;padding-top:15px;padding-bottom:15px;font-size:12px}.navbar-inverse 
        .nav>li>a i{margin-right:3px}}#footer-option{background:#121214;border-top:4px solid #e1e1e1;border-top:5px solid #222;
        font-size:0.9em;padding:20px;position:relative;clear:both}#footer-option .footer-col h3{font-size:20px;font-weight:700;
        text-transform:uppercase;color:#fff}#footer-option .footer-btm{color:#aaa;font-size:13px}#footer-option 
        .footer-btm a{color:#777}#footer-option .footer-btm a:hover{color:#39c}.no-margins{float:left}.stat-percent{float:right}
        .resizable-panels .ibox{clear:none;margin:10px;float:left;overflow:hidden;min-height:150px;min-width:150px}.resizable-panels 
        .ibox .ibox-content{height:calc(100% - 49px)}.ibox{clear:both;margin-bottom:25px;margin-top:0;padding:0}
        .float-e-margins{background-color:#def2ff}.ibox-content h1,.ibox-content h2,.ibox-content h3,.ibox-content h4,.ibox-content h5,
        .ibox-title h1,.ibox-title h2,.ibox-title h3,.ibox-title h4,.ibox-title h5,.ibox-title h6{margin-top:5px}.ibox.collapsed 
        .ibox-content{display:none}.ibox.collapsed .fa.fa-chevron-up:before{content:"\f078"}.ibox.collapsed 
        .fa.fa-chevron-down:before{content:"\f077"}.ibox:after,.ibox:before{display:table}.ibox-title{-moz-border-bottom-colors:none;
        -moz-border-left-colors:none;-moz-border-right-colors:none;-moz-border-top-colors:none;color:inherit;margin-bottom:0;
        padding:14px 15px 7px;min-height:48px}.ibox-content{color:inherit;padding:15px 20px 20px 20px;border-color:#e7eaec;
        border-image:none;border-style:solid solid none;border-width:3px 0}.ibox-content.text-box{padding-bottom:0;padding-top:20px}
        .ibox-footer{color:inherit;border-top:3px solid #e7eaec;font-size:90%;background:#ffffff;padding:10px 15px}.ibox-content
        {min-height:100px}.ibox-heading{background-color:#f3f6fb;border-bottom:none}.ibox-heading h3{font-weight:200;font-size:24px}
        .ibox-title h5{display:inline-block;font-size:16px;margin:0 0 7px;padding:0;text-overflow:ellipsis;float:left}
        .ibox-title h6{display:inline-block;font-size:12px;margin:0 0 7px;padding:0;text-overflow:ellipsis;float:left}
        .ibox-title .label{float:left;margin-left:4px}.ibox-tools{display:block;float:none;margin-top:0;position:relative;padding:0;
        text-align:right}.ibox-tools a{cursor:pointer;margin-left:10px;margin-right:10px;color:#c4c4c4}.ibox-tools a i{font-size:25px;
        color:#676a6c}.ibox-tools a:hover i{color:#d9534f}.ibox-tools a.btn-primary{color:#fff}.strong{font-weight:bold}
        </style>
    </head>
    <body id="boxed">
        <div class="boxed-wrapper">
            <div class="top-bar-light">            
                    <div class="container">
                        <div class="row">
                            <div class="col-sm-5 hidden-xs">
                            </div>
                            <div class="col-sm-7 text-right">
                            </div>
                        </div>
                    </div>
            </div><!--top-bar end here-->
        <!--navigation -->
            <div class="navbar navbar-default navbar-static-top yamm sticky" role="navigation">
                <div class="container">
                    <div class="navbar-header">
                        <center><a class="navbar-brand">Automator Notifier</a></center>
                    </div>
                </div><!--container-->
            </div><!--navbar-default-->
            <div class="breadcrumb-wrap">
                <div class="container">
                    <div class="row">
                        <div class="col-sm-6">
                        </div>
                        <div class="col-sm-6 hidden-xs text-right">
                         </div>
                    </div>
                </div>
            </div><!--breadcrumbs-->   
            <div class="container">                
                <div class="ibox">
                    <div class="ibox-title">
                        <h5><?php echo sprintf(lang('email_forgot_password_heading'), $identity);?></h5>                 
                    </div>
                    <div class="ibox-content">
                     <?php echo sprintf(lang('email_forgot_password_subheading'), anchor('home/reset_password/'. $forgotten_password_code, lang('email_forgot_password_link')));?>
                    </div>
                </div>        
            </div>   


            <footer id="footer-option">
                    <div class="footer-btm text-center">
                    Copyright: ©2025.Estate Management <a href="https://www.nubiaville.com/">Powered by Nubiaville Ltd</a>
                    </div>
            </footer>
        </div>
        </body>
      </html> 
