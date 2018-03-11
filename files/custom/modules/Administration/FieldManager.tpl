//FileManager1
{literal}
    <style type="text/css">
        .postit {
            line-height: 1;
            margin: 5px;
            min-width: 550px;
            min-height:250px;
            padding-top:2px;
            position:relative;
            border:1px solid #E8E8E8;
            border-top:2px solid #fdfd86;
            border-bottom-right-radius: 60px 5px;
            display:inline-block;
            background: #ffff88; /* Old browsers */
            background: -moz-linear-gradient(-45deg, #ffff88 81%, #ffff88 82%, #ffff88 82%, #ffffc6 100%); /* FF3.6+ */
            background: -webkit-gradient(linear, left top, right bottom, color-stop(81%,#ffff88), color-stop(82%,#ffff88), color-stop(82%,#ffff88), color-stop(100%,#ffffc6)); /* Chrome,Safari4+ */
            background: -webkit-linear-gradient(-45deg, #ffff88 81%,#ffff88 82%,#ffff88 82%,#ffffc6 100%); /* Chrome10+,Safari5.1+ */
            background: -o-linear-gradient(-45deg, #ffff88 81%,#ffff88 82%,#ffff88 82%,#ffffc6 100%); /* Opera 11.10+ */
            background: -ms-linear-gradient(-45deg, #ffff88 81%,#ffff88 82%,#ffff88 82%,#ffffc6 100%); /* IE10+ */
            background: linear-gradient(135deg, #ffff88 81%,#ffff88 82%,#ffff88 82%,#ffffc6 100%); /* W3C */
            filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#ffff88', endColorstr='#ffffc6',GradientType=1 ); /* IE6-9 fallback on horizontal gradient */
        }

        .postit:after {
            content: "";
            position:absolute;
            z-index:-1;
            right:-0px; bottom:20px;
            height: 5px;
            background: rgba(0, 0, 0, 0.2);
            box-shadow:2px 15px 5px rgba(0, 0, 0, 0.40);
            -moz-transform: matrix(-1, -0.1, 0, 1, 0, 0);
            -webkit-transform: matrix(-1, -0.1, 0, 1, 0, 0);
            -o-transform: matrix(-1, -0.1, 0, 1, 0, 0);
            -ms-transform: matrix(-1, -0.1, 0, 1, 0, 0);
            transform: matrix(-1, -0.1, 0, 1, 0, 0);
        }
    </style>
{/literal}
<h1>FieldManager {$VERSION}<br>
    <div id="form">
    <form name='processModule' action='index.php'>
        <input type='hidden' name='module' value='Administration'>
        <input type='hidden' name='action' value='FieldManager'>
        {sugar_csrf_form_token}
        <table border=0>
            <tr>
                <td valign='top'>Select all the modules you want to process:</td>
                <td><select name='FM_Module[]' MULTIPLE>{$OPTIONS}</select></td>
            </tr>
            <tr>
                <td>Skip Reports:</td>
                <td><input type='checkbox' name='skipReports'{$REPORTS_CHECKED}></td>
            </tr>
            <tr>
                <td>Skip Workflow/PMSE:</td>
                <td><input type='checkbox' name='skipWorkflow'{$WORKFLOW_CHECKED}></td>
            </tr>
            <tr><td colspan='2'><input type='submit'></td></tr>
        </table></form></div><hr>
{$TABLE}
{literal}
<script language="JavaScript1.4">
    function toggleList(divID) {
    var x = document.getElementById(divID);
    if (x.style.display === "none") {
        x.style.display = "block";
    } else {
        x.style.display = "none";
    }
    var xb = document.getElementById(divID+'_button');
    var txt = xb.innerText;
    if(txt.substr(0,3) == 'See') {
        newText = txt.replace('See', 'Hide');
    } else {
        newText = txt.replace('Hide', 'See');
    }
    xb.innerText = newText;
}
</script>
{/literal}
