var table_obj_list = [];
var id = 0;
var array_button_grade = [];
var is_responsive = false;
var form_plagiarism_alert_change = false;
var form_moderation_agreement_change = false;
var fullload = false;
var display_suspended_gbl   =   0;
var tableobject = 0;

$( document ).ready(function() {



    var langmessage = JSON.parse($('#element_lang_messages').attr('data-lang'));

    var base_url = window.location.origin  + '/mod/coursework/datatables/js/';

    require.config({
        paths: {
            'jquery':                   base_url + 'jquery-3.3.1.min',
            'datatables.net':           base_url + 'jquery.datatables',
            'datatables.searchpanes':   base_url + 'datatables.searchpanes',
            'datatables.buttons':       base_url + 'datatables.buttons',
            'datatables.select':        base_url + 'datatables.select',
            'datatables.responsive':    base_url + 'datatables.responsive.min',
            'jquery-mousewheel': base_url +'jquery.mousewheel',
            'datetimepicker':    base_url + 'jquery.datetimepicker',

        }
    });

    require(['jquery', 'datatables.net'], function ($, DataTable) {

        $.fn.DataTable = DataTable;
        $.fn.DataTableSettings = DataTable.settings;
        $.fn.dataTableExt = DataTable.ext;
        DataTable.$ = $;
        $.fn.DataTable = function ( opts ) {
            return $(this).dataTable( opts ).api();
        };
        $.fn.dataTable.Api.register('row().show()', function() {
            var page_info = this.table().page.info();
            // Get row index
            var new_row_index = this.index();
            // Row position
            var row_position = this.table()
                .rows({ search: 'applied' })[0]
                .indexOf(new_row_index);
            // Already on right page ?
            if ((row_position >= page_info.start && row_position < page_info.end) || row_position < 0) {
                // Return row object
                return this;
            }
            // Find page number
            var page_to_display = Math.floor(row_position / this.table().page.len());
            // Go to that page
            this.table().page(page_to_display);
            // Return row object
            return this;
        });
        require(['jquery', 'datatables.searchpanes'], function($) {
            require(['jquery', 'datatables.select'], function($) {
                require(['jquery', 'datatables.buttons'], function($) {
                    require(['jquery', 'datatables.responsive'], function($) {
                        if(isMobileDevice() && $(window).width() < 768) {
                            is_responsive = true;
                            initDatatable(is_responsive);

                            $('.datatabletest').on('order.dt', function(e) {
                                $('.submissionrowmulti').removeClass("shown");
                            });
                        }
                        else {
                            initDatatable(is_responsive);
                        }
                    });
                });
            });
        });
    });

    /**
     *
     * @param tableid
     */
    function background_load_table(tableid) {
        var tableelement = $('#' + tableid);
        var wrapperelement = tableelement.parent('.dataTables_wrapper');
        var paginationelement = wrapperelement.find('.dataTables_paginate');
        tableobject = table_obj_list[tableid];

        var submissionswrapper =   tableelement.parent('.dataTables_wrapper');



        // hide buttons
        wrapperelement.find('.dataTables_paginate, .dataTables_info, .dataTables_length, .dataTables_filter').css('visibility', 'hidden');
        wrapperelement.find('thead, .dt-button').each(function() {
            var me = $(this);
            me.css('pointer-events', 'none');
            if (me.hasClass('dt-button')) {
                me.find('span').html(' ' + me.find('span').html());
            }
        });
        console.log(submissionswrapper);

        $('<div id="datatable_top_loading_message" class="text-center submission-loading"><i class="fa fa-spinner fa-spin"></i> ' + langmessage.loadingpagination + '</div>').insertBefore(submissionswrapper);
        $('<div class="text-center pagination-loading"><i class="fa fa-spinner fa-spin"></i> ' + langmessage.loadingpagination + '</div>').insertAfter(paginationelement);
        $('<i class="fa fa-spinner fa-spin pagination-loading"></i>').insertBefore(wrapperelement.find('.dt-button > span'));

        // prepare params for ajax request
        var params = {
            group: tableelement.attr('group'),
            perpage: tableelement.attr('perpage'),
            sortby: tableelement.attr('sortby'),
            sorthow: tableelement.attr('sorthow'),
            firstnamealpha: tableelement.attr('firstnamealpha'),
            lastnamealpha: tableelement.attr('lastnamealpha'),
            groupnamealpha: tableelement.attr('groupnamealpha'),
            substatus: tableelement.attr('substatus'),
            unallocated: tableelement.attr('unallocated'),
            courseworkid: tableelement.attr('courseworkid')
        };

        $.ajax({
            url: '/mod/coursework/actions/ajax/datatable/grading.php',
            type: 'POST',
            data: params
        }).done(function(response) {
            console.log('test remove 1');
            $("#datatable_top_loading_message").remove();
            tableobject.rows.add($(response)).draw(false);
          //  move_to_page_initial(tableid);
            wrapperelement.find('.submission-loading').remove();
            // tableobject.searchPanes.rebuildPane();   // => comment out this line in case of removing the filter button with its searchpane
        }).fail(function() {}).always(function() {
            // show buttons
            wrapperelement.find('.pagination-loading').remove();
            wrapperelement.find('.submission-loading').remove();
            wrapperelement.find('thead, .dt-button').css('pointer-events', 'auto');
            wrapperelement.find('.dataTables_paginate, .dataTables_info, .dataTables_length, .dataTables_filter').css('visibility', 'visible');
        });
    }

    function initDatatable(is_responsive) {

        $(".datatabletest").each(function () {

            //class that determines whether all data for the databale has been full loaded
            var fullloaded = $(this).hasClass('full-loaded');

            var table =   $(this).DataTable( {
                'order': [],
                stateSave: true,
                language: {
                    searchPanes: {
                        collapse: {0: $('#search_pane_button').val() || 'Filter', _:($('#search_pane_button').val() || 'Filter')+' (%d)'}
                    }
                },
                buttons:[

                ],
                dom: 'Blfrtip',
                columnDefs:[
                    {
                        searchPanes:{
                            show: false
                        },
                        targets: ['studentname','addition-multiple-button'],
                        bSortable: false
                    },
                    {
                        searchPanes: {
                            show: false
                        },
                        targets: ['lastname_cell','firstname_cell','tableheadpersonaldeadline', 'tableheaddate', 'tableheadfilename', 'tableheadplagiarismalert', 'plagiarism', 'agreedgrade', 'feedbackandgrading', 'provisionalgrade', 'tableheadmoderationagreement']
                    },
                    {
                        searchPanes:{
                            show: true,
                            header: $('#search_pane_group').val() || 'Group',
                        },
                        targets: 'tableheadgroups',
                    },
                    {
                        searchPanes:{
                            show: true,
                            header: $('#search_pane_status').val() || 'Status',
                            getFullText: true,
                        },
                        targets: 'tableheadstatus',
                    },
                    {
                        searchPanes:{
                            show: true,
                            header: $('#search_pane_firstname').val() || 'First Name Initial',
                        },
                        targets: 'firstname_letter_cell',
                    },
                    {
                        searchPanes:{
                            show: true,
                            header: $('#search_pane_lastname').val() || 'Last Name Initial',
                        },
                        targets: 'lastname_letter_cell',
                    },
                    { "visible": false,  "targets": [ 'lastname_letter_cell','firstname_letter_cell', 'lastname_cell','firstname_cell'] }
                ],
                select: {
                    style:    'multi',
                    selector: '.select-checkbox'
                },
                stateSaveParams: function (settings, data) {
                    data.columns = [];
                }

            });

            table_obj_list[$(this).attr('id')] = table;

            if (!fullloaded) {
                background_load_table($(this).attr('id'));
            }




        });

    }

    function move_to_page_initial(table_id) {
        var key = 'datatable_navigate_' + window.location.href + table_id;
        var id = localStorage.getItem(key);
        if (id) {
            table_obj_list[table_id].row('#' + id).show().draw(false);
        }
        // clear id
        localStorage.removeItem(key);
    }

    if(isMobileDevice() && $(window).width() < 768) {
        // For small screens
        var table = $('.datatabletest tbody').on('click', 'td.details-control', function () {
            var tr = $(this).closest("tr");
            var row_id = tr.attr('id').replace('allocatable_', '');
            var table_id = 'assessorfeedbacktable_' + row_id;

            if ($(tr).next('tr.row_assessors').length > 0) {
                $(tr).next('tr.row_assessors').remove();
            }
            else {
                $('<tr class = "submissionrowmultisub row_assessors">'+
                    '<td class="assessors" colspan = "11"><table class="assessors" style="width:95%">' + $('#' + table_id).clone().html() + '</table></td>' +
                    '</tr>').insertAfter($(tr));
            }

            $(tr).toggleClass('shown');
            // $("#" + table_id).toggleClass('tbl_assessor_shown');
            // $("#" + table_id).DataTable({ 'dom': '', 'responsive': true });
        });
    }
    else {
        // Add event listener for opening and closing details
        $('.datatabletest tbody').on('click', 'td.details-control', function () {
            console.log('clicking button');
            var tr = $(this).closest("tr");
            var table_key = $(this).closest('.datatabletest').attr('id');
            var table = table_obj_list[table_key];
            if (table) {
                var row = table.row( tr );

                var row_id = tr.attr('id').replace('allocatable_', '');
                var table_id = 'assessorfeedbacktable_' + row_id;

                if ($('#' + table_id).length > 0) {
                    if ( row.child.isShown() ) {
                        // This row is already open - close it
                        row.child.hide();
                        tr.removeClass('shown');
                    }
                    else {
                        // Open this row
                        // row.child( format(row.data()) ).show();
                        row.child($(
                            '<table class="assessors" width="100%"><tr class = "submissionrowmultisub">'+
                            '<td class="assessors" colspan = "11"><table class="assessors">' + $('#' + table_id).clone().html() + '</table></td>' +
                            '</tr></table>'
                        )).show();
                        tr.addClass('shown');
                    }
                }
            }
        });
    }


    $('.datatabletest').on('click', '.splitter-firstname, .splitter-lastname, .splitter-email', function (event) {
        event.preventDefault();
        var node = $(event.target),
            isAscending = node.hasClass('sorting_asc'),
            currentsort = 'asc', sortby = 'desc';
        if (!isAscending) {
            currentsort = 'desc';
            sortby = 'asc';
        }

        //node.closest('#example').DataTable()
        //    .order( [ sortColumn, sortby ] )
        //    .draw();
        var table_id = $(this).closest('.datatabletest').attr('id');
        table = table_obj_list[table_id];
        var headerclass = $(this).hasClass('splitter-firstname') ? 'firstname_cell' : 'lastname_cell';
        headerclass = $(this).hasClass('splitter-email') ? 'email_cell' : headerclass;
        console.log(headerclass);
        var sortColumn = table.column('.' + headerclass).index();
console.log(sortColumn);
        console.log(sortby);
        console.log(table);
        table.order([sortColumn, sortby]).draw();

        node.addClass('sorting_' + sortby).removeClass('sorting sorting_' + currentsort);
        node.parent().removeClass('sorting sorting_asc sorting_desc');
        node.siblings().removeClass('sorting_asc sorting_desc').addClass('sorting');
    });


});


function isMobileDevice() {
    if ( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
        return true;
    }
    return false;
}
