<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2');

echo'
<style>

                    .btn:hover {
                        -webkit-transform: scale(1.1);
                        -moz-transform: scale(1.1);
                        -o-transform: scale(1.1);
                    }
                    .btn {
                        -webkit-transform: scale(0.8);
                        -moz-transform: scale(0.8);
                        -o-transform: scale(0.8);
                        -webkit-transition-duration: 0.5s;
                        -moz-transition-duration: 0.5s;
                        -o-transition-duration: 0.5s;
                    }
                    </style>   
<section class="services-wrap">        
<div class="container">
    <div class="ibox">
        <div class="ibox-title">
            <h5>Add POS Batch Process</h5>
            <div class="ibox-tools">
                
                <a href="'.site_url('Setup/view_pos_batch').'" data-toggle="tooltip" 
                    data-placement="bottom" title="" data-original-title="Return">
                    <button type="button" class="btn rounded-pill btn btn-danger rounded">
                    <span class="glyphicon glyphicon-repeat" aria-hidden="true"></span>
                    <span><strong>Return</strong></span>  
                </button>
                </a>                        
            </div>
        </div>
        <div class="ibox-content">
             
                    '.form_open('Setup/add_pos_item/edit/'.$main_data->batchcode,
                    array('name'=>'setup_form','class'=>'form-horizontal')).'              
                <div class="col-md-12">
                    <input type="hidden" name="'.$this->security->get_csrf_token_name()
                        .'" value="'.$this->security->get_csrf_hash().'" />                    
                    <input type="hidden" value="'.$main_data->batchcode.'" name="id"/> 
                </div>            
               
                
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Batch Code:</label>
                        <div class="col-sm-8"><input name="batchcode" type="text" class="form-control" 
                        value="'.$main_data->batchcode.'" placeholder="Enter batchcode" readonly="readonly"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label class="col-sm-4 control-label">Procurement Code:</label>
                        <div class="col-sm-8"><input name="procurementcode" type="text" class="form-control" 
                        value="'.$main_data->procurementcode.'" placeholder="Customer Code"></div>
                    </div>
                </div>
                <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Request date:</label>
                    <div class="col-sm-8"><input name="request_date" type="text" class="form-control datepicker" 
                    value="'.$main_data->request_date.'" placeholder="Enter Date"></div>
                </div>
            </div>
                           
               
                <div class="col-md-6">
                <div class="form-group"><label class="col-sm-4 control-label">Location:</label>
                    <div class="col-sm-8"><input name="location" type="text" class="form-control" 
                    value="'.$main_data->location.'" placeholder="Enter Location"></div>
                </div>
            </div>                        
                <div class="col-md-12 divide20"></div>                 
            </form>  
        </div>       
        
    </div>      
    
    <div class="ibox">
        <div class="ibox-title"><h5>POS Order List</h5>
            <div class="ibox-tools">
                <a href="javascript:add_row()" data-toggle="tooltip" data-placement="bottom" 
                    title="" data-original-title="Add New POS Batch Detail">
                    
                    <button type="button" class=" btn rounded-pill btn btn-primary rounded">
                            
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                        <span><strong>Add</strong></span>  
                        </button>
                </a>                   
                <a href="javascript:delete_row()" data-toggle="tooltip" data-placement="bottom" 
                title="" data-original-title="Delete Selected">
                <button type="button" class="btn rounded-pill btn btn-danger rounded">
                <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
                <span><strong>Delete</strong></span>  
            </button>
                </a>                                    
            </div>
        </div>
            <div class="ibox-content">
                <div class="table-responsive">
                     <table id="example" class="display table table-hover table-striped"  width="100%">
                         <thead>
                           <tr>
                            <th>Sno</th>
                            <th>Batch Code</th>
                            <th>Item Type</th>
                            <th>POS Description</th>
                             <th>Quantity</th>
                           
                            <th></th>
                            <th></th>
                           </tr>                           
                         </thead>
                         <tbody>
                         </tbody>
                       </table>
                 </div>
            </div>
        </div>        
    <!-- Modal -->
    <div class="modal fade" id="modal_form" tabindex="-1" role="dialog" 
         aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header">
                    <button type="button" class="close" 
                       data-dismiss="modal">
                           <span aria-hidden="true">&times;</span>
                          <span class="sr-only">Close</span>
                    </button>
                    <h4 class="modal-title">Modal title</h4>
                </div>
                <!-- Modal Body -->
                <div class="modal-body">
                    <form action="#" id="form" class="form-horizontal">
                        <input type="hidden" name="'.$this->security->get_csrf_token_name()
                            .'" value="'.$this->security->get_csrf_hash().'" />                    
                        <input type="hidden" value="" name="id"/> 
                        <input type="hidden" value="" name="sno"/>                               
                            <input type="hidden"  value="'.$main_data->batchcode.'" name="batchcode" class="form-control" 
                                placeholder="Enter Code"/>   
                                                        
                           
                        <div class="form-group"><label class="col-sm-4 control-label">Item Type:</label>
                        <div class="col-sm-8">
                            <input type="text" name="pos" class="form-control" 
                            placeholder="Enter Type"/>                           
                        </div>
                    </div>   

                      
                                                    
                               
                        <div class="form-group"><label class="col-sm-4 control-label">POS Description  :</label>
                            <div class="col-sm-8">
                                <input type="text" name="pos_desc" class="form-control" 
                                placeholder="POS Description"/>                           
                            </div>
                        </div>  
                        <div class="form-group"><label class="col-sm-4 control-label">Quantity  :</label>
                            <div class="col-sm-8">
                                <input type="text" name="qty" class="form-control" 
                                placeholder="Quantity"/>                           
                            </div>
                        </div>                        
                    </form>
                </div>
                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" id="btnSave" onclick="save()" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
</section>    
';
       