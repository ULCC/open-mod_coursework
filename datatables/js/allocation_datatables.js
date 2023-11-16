/**
 * This defines all javascript needed in the coursework module.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link http://ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


M.mod_coursework_datatables = {




    /**
     * This is to set up the listeners etc for the page elements on the allocations page.
     */
    init_allocate_page: function (e, courseworkid, numberofmarkers, pagesize) {

        var options = {
            'courseworkid' :courseworkid,
            'group':0,
            'sortby':'',
            'sorthow':'',
            'perpage':10
        };

        $('.allocations').each(function() {
            if(jQuery().DataTable()) {
                console.log('Datatables loaded');
            } else {
                console.log('Datatables not loaded');
            }
            var numbersofcolumns =  $("table").find("th:first td").length;

            numberofmarkers= parseInt(numberofmarkers);
            var tableobject     = $(this).DataTable({
                "language": {
                    "search": "Search _INPUT_ use \"\" for exact word search"
                },
                "pageLength": pagesize,
                "columnDefs": [{
                    "targets": [1,numberofmarkers],
                    "render": function(data, type) {
                        if (type === 'filter') {
                            cellelements = $(data);
                            assessortext = cellelements.find('a').first().text().trim();
                            if (assessortext == "") {
                                assessortext = "";
                            }
                            return assessortext;
                        } else {

                            return data;
                        }

                    }

                }]
            });

            var tableelement    =   $(this);

            var wrapperelement = tableelement.parent('.dataTables_wrapper');
            var paginationelement = wrapperelement.find('.dataTables_paginate');

            // hide buttons
            wrapperelement.find('.dataTables_paginate, .dataTables_info, .dataTables_length, .dataTables_filter').css('visibility', 'hidden');
            wrapperelement.find('thead, .dt-button').each(function() {
                var me = $(this);
                me.css('pointer-events', 'none');
                if (me.hasClass('dt-button')) {
                    me.find('span').html(' ' + me.find('span').html());
                }
            });
            $('<div class="text-center pagination-loading"><i class="fa fa-spinner fa-spin"></i>loading</div>').insertAfter(paginationelement);
            $('<i class="fa fa-spinner fa-spin pagination-loading"></i>').insertBefore(wrapperelement.find('.dt-button > span'));


            $.ajax({
                url: '/mod/coursework/actions/ajax/datatable/allocation.php',
                type: 'POST',
                data: options
            }).done(function(response) {

                var tablerows   =   "";
                //the table rows returned often have text spaces that jquery turns into objects
                //we dont like this so remove all elements in response of type text node
                 tablerows = $(response).filter(function() { return this.nodeType != Node.TEXT_NODE; });
                tableobject.rows.add($(tablerows)).draw(false);
                wrapperelement.find('.pagination-loading').remove();
                wrapperelement.find('thead, .dt-button').css('pointer-events', 'auto');
                wrapperelement.find('.dataTables_paginate, .dataTables_info, .dataTables_length, .dataTables_filter').css('visibility', 'visible');

            });

            //aatach all event handlers to elements in the datatable after the datatable has been drawn
            tableobject.on('draw',function (i,e) {
                M.mod_coursework_datatables.init_allocation_dropdowns(courseworkid);
                M.mod_coursework_datatables.init_allocation_pin_checkboxes(courseworkid);
                M.mod_coursework_datatables.init_sample_set_checkboxes(courseworkid);
            })

            M.mod_coursework_datatables.init_allocation_checkboxes();



        });
    },

    init_allocation_dropdowns:    function(courseworkid) {


        $('.assessor_id_dropdown').change(function () {

        });



        $('.assessor_id_dropdown').each(function (e) {



            var allocationoptions = {
                'courseworkid': courseworkid,
                'allocatableinfo': $(this).attr('id')
            };

            //unbind any change events as we call rebind on every load we need to make sure
            //this code is only called once per change
            $(this).unbind('change');

            $(this).change(function () {

                var $dropdown = $(this);
                var $checkbox = $dropdown.prevAll('.sampling_set_checkbox');
                var isAssessorSelectedAlready  = [];

                var $currentselection = $dropdown.attr('id');

                if ($checkbox.length) {
                    if ($dropdown.val() === '') {
                        $checkbox.prop('checked', false);
                    } else {
                        $checkbox.prop('checked', true);
                    }
                }

                var $row = $dropdown.closest('tr');
                var $selected_val = $dropdown.val();

                $row.find('td').each(function () {

                    // dropdown
                    var $celldropdown = $(this).find('.assessor_id_dropdown');
                    var $celldropdown_id = $celldropdown.attr('id');
                    var $celldropdown_val = $celldropdown.val();
                    // link
                    var $atag = $(this).find('a');
                    var $id_from_label = $atag.data('assessorid');

                    if ($currentselection != $celldropdown_id && ($celldropdown_val == $selected_val || $id_from_label == $selected_val)) {
                        // alert('Assessor already allocated. \n Choose different assessor.');
                        $('<div id="same_assessor" class="alert">' + M.util.get_string('sameassessorerror', 'coursework') + '</div>').insertAfter($('#' + $currentselection));
                        $dropdown.val('');
                        isAssessorSelectedAlready.push(true);
                    } else if ($dropdown.val() != '') {
                        $("#same_assessor").remove();
                    }
                });


                if ($.inArray(true,isAssessorSelectedAlready) == -1) {
                    processingicon = $('<div class="text-center pagination-loading"><i class="fa fa-spinner fa-spin"></i>processing</div>').insertAfter($(this));
                    allocationoptions.value = $(this).val();
                    var dropdown = $(this);

                    $.ajax({
                        url: '/mod/coursework/actions/update_allocated_assessor.php',
                        type: 'POST',
                        data: allocationoptions
                    }).done(function (response) {
                        var saveresponse = JSON.parse(response);

                        if (saveresponse.result != 'false') {

                            var parentcell = $(dropdown).parent();

                            var loadedcells = $(saveresponse.content).filter(function () {
                                return this.nodeType != Node.TEXT_NODE;
                            });

                            loadedcells.each(function (index, el) {

                                if ($(el).attr('class') == $(dropdown).parent().attr('class')) {
                                    parentcell.empty();
                                    parentcell.html($(el).html());
                                }
                            });

                            M.mod_coursework_datatables.init_allocation_dropdowns(courseworkid);
                            M.mod_coursework_datatables.init_allocation_pin_checkboxes(courseworkid);
                            M.mod_coursework_datatables.init_sample_set_checkboxes(courseworkid);

                        }
                        processingicon.remove();
                        processingicon.empty();

                    });
                }
            });


        });


    },

    init_allocation_pin_checkboxes: function(courseworkid)  {
        $('.pin_1').each(function(index, element) {

            //unbind any click events as we call rebind on every load we need to make sure
            //this code is only called once per click
            $(element).unbind('click');

            $(element).click(function () {


                pinned  =   ($(element).prop('checked') == true) ? 1 : 0;

                pinneddata  =   {

                    'allocatableinfo': $(element).prop('name').replaceAll('[',':').replaceAll(']',''),
                    'courseworkid': courseworkid,
                    'pinned': pinned
                }


                $.ajax({
                    url: '/mod/coursework/actions/update_allocated_pinned.php',
                    type: 'POST',
                    data: pinneddata
                }).done(function (response) {

                });
            });


        });


        $('.pin_2').each(function(index, element) {

            //unbind any click events as we call rebind on every load we need to make sure
            //this code is only called once per click
            $(element).unbind('click');

            $(element).click(function () {


                pinned  =   ($(element).prop('checked') == true) ? 1 : 0;

                pinneddata  =   {

                    'allocatableinfo': $(element).prop('name').replaceAll('[',':').replaceAll(']',''),
                    'courseworkid': courseworkid,
                    'pinned': pinned
                }


                $.ajax({
                    url: '/mod/coursework/actions/update_allocated_pinned.php',
                    type: 'POST',
                    data: pinneddata
                }).done(function (response) {

                });
            });


        });


        $('.pin_3').each(function(index, element) {

            //unbind any click events as we call rebind on every load we need to make sure
            //this code is only called once per click
            $(element).unbind('click');

            $(element).click(function () {


                pinned  =   ($(element).prop('checked') == true) ? 1 : 0;

                pinneddata  =   {

                    'allocatableinfo': $(element).prop('name').replaceAll('[',':').replaceAll(']',''),
                    'courseworkid': courseworkid,
                    'pinned': pinned
                }


                $.ajax({
                    url: '/mod/coursework/actions/update_allocated_pinned.php',
                    type: 'POST',
                    data: pinneddata
                }).done(function (response) {

                });
            });


        });
    },


    init_allocation_checkboxes: function()  {

        if (!$('.pin_1')) {
            $(this).hide();
        }

        //stop propagation of click event when select all asessor for column is clicked in header
        $('#selectall_1').click(function (e) {
            e.stopPropagation();
        });

        $('#selectall_2').click(function (e) {
            e.stopPropagation();
        });

        $('#selectall_3').click(function (e) {
            e.stopPropagation();
        });



        var datatable   =    $('#allocation_table').DataTable();

        $('#selectall_1').change(function () {

            //get datatables object
            //check or uncheck all pin boxes regardless of whether they are on screen or not

            var ischecked = $(this).is(":checked");

            datatable.$('.pin_1').each(function(index, element) {
                if (ischecked) {
                    $(element).prop('checked', true);

                } else {
                    $(element).prop('checked', false);
                }
            });

        });

        $('#selectall_2').change(function () {

            var ischecked = $(this).is(":checked");

            datatable.$('.pin_2').each(function(index, element) {
                if (ischecked) {
                    $(element).prop('checked', true);

                } else {
                    $(element).prop('checked', false);
                }
            });
        });

        $('#selectall_3').change(function () {

            var ischecked = $(this).is(":checked");

            datatable.$('.pin_3').each(function(index, element) {
                if (ischecked) {
                    $(element).prop('checked', true);

                } else {
                    $(element).prop('checked', false);
                }
            });
        });

        $('#selectall_mod').change(function () {
            var ischecked = $(this).is(":checked");

            datatable.$('.pin_r').each(function(index, element) {
                if (ischecked) {
                    $(element).prop('checked', true);

                } else {
                    $(element).prop('checked', false);
                }
            });

        });
    },


    init_sample_set_checkboxes: function (courseworkid) {

        // Unchecked 'Include in sample' checkbox disables
        // dropdown automatically.
        $('.sampling_set_checkbox').click(function (index,element) {

            var $checkbox = $(this);
            var $dropdown = $checkbox.nextAll('.assessor_id_dropdown');

            var $pinned = $checkbox.nextAll('.existing-assessor');
            var $child = $pinned.children('.pinned');

            var insampleset  =   ($checkbox.prop('checked') == true) ? 1 : 0;

            if ($dropdown.length) {
                if (insampleset) {
                    $dropdown.prop("disabled", false);
                    $child.prop("disabled", false);

                } else {
                    $dropdown.val('');
                    $dropdown.prop("disabled", true);
                    $child.prop("disabled", true);
                    $("#same_assessor").remove();

                    $(this).siblings().each(function(index,element)  {
                       if ($(this).hasClass('existing-assessor')) {
                           $(this).remove();
                       }
                    });
                }

                allocstablesampledata  =   {

                    'allocatableinfo': $(this).prop('name').replaceAll('[',':').replaceAll(']',''),
                    'courseworkid': courseworkid,
                    'insample': insampleset
                }


                $.ajax({
                    url: '/mod/coursework/actions/update_allocatable_in_sample.php',
                    type: 'POST',
                    data: allocstablesampledata
                }).done(function (response) {

                });
            }
        });


        $('.sampling_set_checkbox').each(function () {

            var $checkbox = $(this);

            var $assessddname = $checkbox.attr('id').replace('_samplecheckbox', '');

            var $assessdd = $('#' + $assessddname);

            if ($checkbox.is(":checked")) {
                $assessdd.prop("disabled", false)
            } else {
                $assessdd.prop("disabled", true);
            }

        });


    }
}
