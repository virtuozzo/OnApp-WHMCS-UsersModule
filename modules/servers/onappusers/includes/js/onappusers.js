function buildFields(ServersData) {
    // Clean up table & store titles
    var table;
    if ($('div#tab3').length) {
        table = $('div#tab3 table.form').eq(1);
    } else {
        table = $('table').eq(5);
    }
    table.find('tr').remove();

    // if no servers in group
    if (ServersData.NoServers) {
        html = '<tr><td colspan="2" class="fieldlabel">' + ServersData.NoServers + '</td></tr>';
        table.append(html);
        return;
    }

    // Proceed data and create select lists
    var cnt = 0;
    for (server_id in ServersData) {
        if (server_id != parseInt(server_id)) {
            continue;
        }

        server = ServersData[server_id];

        var versionsInfo = ' (Module version: ' + ONAPP_LANG['custom_moduleVersion'] + ', ' + 'OnApp PHP Wrapper version: ' + ONAPP_LANG['custom_wrapperVersion'] + ', ' + 'OnApp CP version: ' + ONAPP_LANG['custom_apiVersion'] + ')';

        // server name
        html = '<tr><td colspan="2" class="fieldarea"><b>' + server.Name + versionsInfo + '</b></td></tr>';
        table.append(html);

        // if no servers in group
        if (ServersData[server_id].NoAddress) {
            html = '<tr><td colspan="2" class="fieldlabel">' + ServersData[server_id].NoAddress + '</td></tr>';
            table.append(html);
            continue;
        }

        // billing plans row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbindingplanstitle + '</td>';
        html += '<td class="fieldarea" id="plan' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // custom billing plans/buckets
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersusecustombillingplans + '</td>';
        html += '<td class="fieldarea" id="custombillingplans' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // prefix for custom billing plans/buckets
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersprefixforcustombillingplans + '</td>';
        html += '<td class="fieldarea" id="prefixforcustombillingplans' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // suspended billing plans row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbindingplanssuspendedtitle + '</td>';
        html += '<td class="fieldarea" id="suspendedplan' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // roles row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbindingrolestitle + '</td>';
        html += '<td class="fieldarea" id="role' + server_id + '" rel="' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // TZs row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappuserstimezonetitle + '</td>';
        html += '<td class="fieldarea" id="tz' + server_id + '" rel="' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // user groups row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersusergroupstitle + '</td>';
        html += '<td class="fieldarea" id="usergroups' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // locale row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbindinglocaletitle + '</td>';
        html += '<td class="fieldarea" id="locale' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // pass taxes row
        html = '<tr style="display: none;">';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappuserspassthrutaxes + '</td>';
        html += '<td class="fieldarea" id="passtaxes' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // show duedate row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappuserssetduedatetocurrent + '</td>';
        html += '<td class="fieldarea" id="duedate' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // show Billing Type row
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersbillingtype + '</td>';
        html += '<td class="fieldarea" id="billingtype' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        // show Unsuspend on a positive Credit Balance
        html = '<tr>';
        html += '<td class="fieldlabel">' + ONAPP_LANG.onappusersunsuspendonpositivebalance + '</td>';
        html += '<td class="fieldarea" id="unsuspendonpositivebalance' + server_id + '"></td></tr>';
        table.find('tr:last').after(html);

        var isServerGroupCorrect = ServersData.Group == $("select[name$='servergroup']").val();

        // process biling plans
        if (typeof server.BillingPlans == 'object') {
            select = $('<select name="bills_packageconfigoption' + ++cnt + '"></select>');

            for (plan_id in server.BillingPlans) {
                plan = server.BillingPlans[plan_id];

                $(select).append($('<option>', {value: server_id + ':' + plan_id}).text(plan));
            }

            // select selected plans
            if (ServersData.SelectedPlans) {
                if (isServerGroupCorrect) {
                    $(select).val(server_id + ':' + ServersData.SelectedPlans[server_id]);
                }
            }
        }
        else {
            select = server.BillingPlans;
        }
        $('#plan' + server_id).html(select);

        // process custom billing plans/buckets
        input = $('<input name="custombillingplans_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="checkbox" />');
        // checkbox state
        if (ServersData.CustomBillingPlansCurrent) {
            if (isServerGroupCorrect) {
                if (ServersData.CustomBillingPlansCurrent[server_id]) {
                    $(input).attr('checked', true);
                }
            }
        }
        $('#custombillingplans' + server_id).html(input);

        // process prefix for custom billing plans/buckets
        input = $('<input name="prefixforcustombillingplans_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="text" />');
        if (ServersData.PrefixForCustomBillingPlansCurrent) {
            if (isServerGroupCorrect) {
                if (ServersData.PrefixForCustomBillingPlansCurrent[server_id]) {
                    $(input).val(ServersData.PrefixForCustomBillingPlansCurrent[server_id]);
                }
            }
        }
        $('#prefixforcustombillingplans' + server_id).html(input);

        // process suspended biling plans
        if (typeof server.BillingPlans == 'object') {
            select = $('<select name="suspendedbills_packageconfigoption' + ++cnt + '"></select>');

            for (plan_id in server.SuspendedBillingPlans) {
                plan = server.SuspendedBillingPlans[plan_id];

                $(select).append($('<option>', {value: server_id + ':' + plan_id}).text(plan));
            }

            // select selected plans
            if (ServersData.SelectedSuspendedPlans) {
                if (isServerGroupCorrect) {
                    $(select).val(server_id + ':' + ServersData.SelectedSuspendedPlans[server_id]);
                }
            }
        }
        else {
            select = server.SuspendedBillingPlans;
        }
        $('#suspendedplan' + server_id).html(select);

        // process roles
        if (typeof server.Roles == 'object') {
            html = '';
            for (role_id in server.Roles) {
                html += '<input type="checkbox" name="roles_packageconfigoption'
                    + ++cnt + '" value="' + role_id + '"/> ' + server.Roles[role_id] + '<br/>';
            }
            $('#role' + server_id).append(html);

            // select selected roles
            if (ServersData.SelectedRoles) {
                if (isServerGroupCorrect) {
                    chk = ServersData.SelectedRoles[server_id];
                    for (i in chk) {
                        $("#role" + server_id + " input[value='" + chk[i] + "']").attr('checked', true);
                    }
                }
            }
        }
        else {
            $('#role' + server_id).append(server.Roles);
        }

        // process TZs
        select = $('<select name="tzs_packageconfigoption' + ++cnt + '"></select>');
        select = select.html(OnAppUsersTZs);
        $('#tz' + server_id).html(select);
        // select selected TZ
        if (ServersData.SelectedTZs) {
            if (isServerGroupCorrect) {
                $(select).val(ServersData.SelectedTZs[server_id]);
            }
        }

        // process user groups
        if (typeof server.UserGroups == 'object') {
            select = $('<select name="usergroups_packageconfigoption' + ++cnt + '"></select>');

            for (group_id in server.UserGroups) {
                plan = server.UserGroups[group_id];

                $(select).append($('<option>', {value: server_id + ':' + group_id}).text(plan));
            }

            // select selected plans
            if (ServersData.SelectedUserGroups) {
                if (isServerGroupCorrect) {
                    $(select).val(server_id + ':' + ServersData.SelectedUserGroups[server_id]);
                }
            }
        }
        else {
            select = server.UserGroups;
        }
        $('#usergroups' + server_id).html(select);

        // process locale
        if (typeof server.Locales == 'object' && Object.size(server.Locales)) {
            select = $('<select name="locale_packageconfigoption' + ++cnt + '"></select>');

            for (code in server.Locales) {
                locale = server.Locales[code];

                $(select).append($('<option>', {value: server_id + ':' + code}).text(locale));
            }

            // select selected locale
            if (ServersData.SelectedLocales) {
                if (isServerGroupCorrect) {
                    $(select).val(server_id + ':' + ServersData.SelectedLocales[server_id]);
                }
            }
        }
        else {
            select = $('<input name="locale_packageconfigoption' + ++cnt + '" rel="' + server_id + '" />');
            // select selected locale
            if (ServersData.SelectedLocales && ServersData.SelectedLocales[server_id]) {
                $(select).val(ServersData.SelectedLocales[server_id]);
            }
            else {
                $(select).val('en');
            }
        }
        $('#locale' + server_id).html(select);

        // process passthru taxes to OnApp
        input = $('<input name="passtaxes_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="checkbox" />');
        // checkbox state
        if (ServersData.PassTaxes) {
            if (isServerGroupCorrect) {
                if (ServersData.PassTaxes[server_id]) {
                    $(input).attr('checked', true);
                }
            }
        }
        $('#passtaxes' + server_id).html(input);

        // process due date
        input = $('<input name="duedate_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="checkbox" />');
        // checkbox state
        if (ServersData.DueDateCurrent) {
            if (isServerGroupCorrect) {
                if (ServersData.DueDateCurrent[server_id]) {
                    $(input).attr('checked', true);
                }
            }
        }
        $('#duedate' + server_id).html(input);

        // process Billing Type
        select = $('<select name="billingtype_packageconfigoption' + ++cnt + '"></select>');
        $(select).append($('<option>', {value: server_id + ':0'}).text(ONAPP_LANG.onappusersbillingtypepostpaid));
        $(select).append($('<option>', {value: server_id + ':1'}).text(ONAPP_LANG.onappusersbillingtypeprepaid));
        if (isServerGroupCorrect) {
            $(select).val(server_id + ':' + (ServersData.SelectedBillingType ? ServersData.SelectedBillingType[server_id] : '0'));
        }
        $('#billingtype' + server_id).html(select);

        // process Unsuspend on a positive Credit Balance
        input = $('<input name="unsuspendonpositivebalance_packageconfigoption' + ++cnt + '" rel="' + server_id + '" type="checkbox" />');
        // checkbox state
        if (ServersData.UnsuspendOnPositiveBalance) {
            if (isServerGroupCorrect) {
                if (ServersData.UnsuspendOnPositiveBalance[server_id]) {
                    $(input).attr('checked', true);
                }
            }
        }
        $('#unsuspendonpositivebalance' + server_id).html(input);
    }

    // input for storing selected values
    html = '<tr><td colspan="2" class="fieldarea">';
    html += '<input type="text" name="packageconfigoption[1]" id="bp2s" value="" size="230" />';
    html += '</td></tr>';

    table.append($(html));
    table.find('tr:last').hide();
    table.find('tr').eq(1).find('td').eq(0).css('width', 150);

    // handle storing selected values
    storeSelectedPlans();
    $("select[name^='bills_packageconfigoption']").bind('change', function () {
        storeSelectedPlans();
    });

    storeCustomBillingPlansCurrent();
    $("input[name^='custombillingplans_packageconfigoption']").bind('change', function () {
        storeCustomBillingPlansCurrent();
    });

    storePrefixForCustomBillingPlansCurrent();
    $("input[name^='prefixforcustombillingplans_packageconfigoption']").bind('change', function () {
        storePrefixForCustomBillingPlansCurrent();
    });

    storeSelectedSuspendedPlans();
    $("select[name^='suspendedbills_packageconfigoption']").bind('change', function () {
        storeSelectedSuspendedPlans();
    });

    storeSelectedRoles();
    $("input[name^='roles_packageconfigoption']").bind('change', function () {
        storeSelectedRoles();
    });

    storeSelectedTZs();
    $("select[name^='tzs_packageconfigoption']").bind('change', function () {
        storeSelectedTZs();
    });

    storeSelectedUserGroups();
    $("select[name^='usergroups_packageconfigoption']").bind('change', function () {
        storeSelectedUserGroups();
    });

    storeSelectedLocales();
    $("select[name^='locale_packageconfigoption']").bind('change', function () {
        storeSelectedLocales();
    });

    storePassTaxes();
    $("input[name^='passtaxes_packageconfigoption']").bind('change', function () {
        storePassTaxes();
    });

    storeDueDateCurrent();
    $("input[name^='duedate_packageconfigoption']").bind('change', function () {
        storeDueDateCurrent();
    });

    storeSelectedBillingType();
    $("select[name^='billingtype_packageconfigoption']").bind('change', function () {
        storeSelectedBillingType();
    });

    storeUnsuspendOnPositiveBalance();
    $("input[name^='unsuspendonpositivebalance_packageconfigoption']").bind('change', function () {
        storeUnsuspendOnPositiveBalance();
    });

    // align dropdown lists
    alignSelects();
}

var OnAppUsersData = {
    SelectedPlans: {},
    CustomBillingPlansCurrent: {},
    PrefixForCustomBillingPlansCurrent: {},
    SelectedSuspendedPlans: {},
    SelectedRoles: {},
    SelectedTZs: {},
    SelectedUserGroups: {},
    SelectedLocales: {},
    PassTaxes: {},
    DueDateCurrent: {},
    SelectedBillingType: {},
    UnsuspendOnPositiveBalance: {}
};

function storeCustomBillingPlansCurrent() {
    $("input[name^='custombillingplans_packageconfigoption']").each(function (i, val) {
        var index = $(val).attr('rel');
        OnAppUsersData.CustomBillingPlansCurrent[index] = $(val).prop('checked') ? 1 : 0;
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storePrefixForCustomBillingPlansCurrent() {
    $("input[name^='prefixforcustombillingplans_packageconfigoption']").each(function (i, val) {
        var index = $(val).attr('rel');
        OnAppUsersData.PrefixForCustomBillingPlansCurrent[index] = $(val).val();
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeDueDateCurrent() {
    $("input[name^='duedate_packageconfigoption']").each(function (i, val) {
        var index = $(val).attr('rel');
        OnAppUsersData.DueDateCurrent[index] = $(val).prop('checked') ? 1 : 0;
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storePassTaxes() {
    $("input[name^='passtaxes_packageconfigoption']").each(function (i, val) {
        var index = $(val).attr('rel');
        OnAppUsersData.PassTaxes[index] = $(val).prop('checked') ? 1 : 0;
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeUnsuspendOnPositiveBalance() {
    $("input[name^='unsuspendonpositivebalance_packageconfigoption']").each(function (i, val) {
        var index = $(val).attr('rel');
        OnAppUsersData.UnsuspendOnPositiveBalance[index] = $(val).prop('checked') ? 1 : 0;
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeSelectedLocales() {
    if ($("select[name^='locale_packageconfigoption']").length) {
        $("select[name^='locale_packageconfigoption']").each(function (i, val) {
            var tmp = val.value.split(':');
            OnAppUsersData.SelectedLocales[tmp[0]] = tmp[1];
        });
    }
    else {
        $("input[name^='locale_packageconfigoption']").each(function (i, val) {
            var index = $(val).attr('rel');
            OnAppUsersData.SelectedLocales[index] = val.value;
        });
    }

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeSelectedPlans() {
    $("select[name^='bills_packageconfigoption']").each(function (i, val) {
        var tmp = val.value.split(':');
        OnAppUsersData.SelectedPlans[tmp[0]] = tmp[1];
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeSelectedSuspendedPlans() {
    $("select[name^='suspendedbills_packageconfigoption']").each(function (i, val) {
        var tmp = val.value.split(':');
        OnAppUsersData.SelectedSuspendedPlans[tmp[0]] = tmp[1];
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeSelectedRoles() {
    OnAppUsersData.SelectedRoles = {};
    $("input[name^='roles_packageconfigoption']").each(function (i, val) {
        if (!$(val).prop('checked')) {
            return;
        }
        var index = $(val).parents('td').attr('rel');

        if (typeof OnAppUsersData.SelectedRoles[index] == 'undefined') {
            OnAppUsersData.SelectedRoles[index] = [];
        }

        if (jQuery.inArray(val.value, OnAppUsersData.SelectedRoles[index]) == -1) {
            OnAppUsersData.SelectedRoles[index].push(val.value);
        }
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeSelectedTZs() {
    $("select[name^='tzs_packageconfigoption']").each(function (i, val) {
        var index = $(val).parents('td').attr('rel');
        OnAppUsersData.SelectedTZs[index] = val.value;
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeSelectedUserGroups() {
    $("select[name^='usergroups_packageconfigoption']").each(function (i, val) {
        var tmp = val.value.split(':');
        OnAppUsersData.SelectedUserGroups[tmp[0]] = tmp[1];
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function storeSelectedBillingType() {
    $("select[name^='billingtype_packageconfigoption']").each(function (i, val) {
        var tmp = val.value.split(':');
        OnAppUsersData.SelectedBillingType[tmp[0]] = tmp[1];
    });

    $("input[name^='packageconfigoption[1]']").val(objectToString(OnAppUsersData));
}

function alignSelects() {
    if (!$("select[name='servertype']:visible").length) {
        return;
    }

    var max = 0;
    $('div#tab2box select').each(function (i, val) {
        width = $(val).width();
        if (width > max) {
            max = width + 30;
        }
    });
    $('div#tab2box select').css('min-width', max);
    $("div#tab2box input[name^='locale_packageconfigoption']").css('min-width', max - 5);
}

$(document).ready(function () {
    // Refresh data if server group was changed
    $("select[name$='servergroup']").bind('change', function () {
        $('table').eq(5).find('tr:first td').html(ONAPP_LANG.onappusersjsloadingdata);
        $.ajax({
            url: document.location.href,
            data: 'servergroup=' + this.value,
            success: function (data) {
                buildFields(jQuery.evalJSON(data));
            }
        });
    });

    // fill the table with datas
    //buildFields( ServersData );

    $('li#tab2').bind('click', function () {
        alignSelects();
    });
});

function objectToString(o) {
    return JSON.stringify(o);
}

Object.size = function (obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) {
            size++;
        }
    }
    return size;
};
