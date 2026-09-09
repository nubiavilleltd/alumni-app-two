<?php defined('BASEPATH') OR exit('No direct script access allowed');
$this->load->view('home/splash2'); echo'
<section class="services-wrap">
  <div class="nk-content nk-content-fluid">
    <div class="container-xl wide-xl">
      <div class="nk-content-inner">
        <div class="nk-content-body">
          <div class="components-preview">
            <div class="nk-block-head nk-block-head-sm">
              <div class="nk-block-between">
                <div class="nk-block-head-content">
                  <h4 class="nk-block-title">
                  Customer Setup
                  </h4>
                </div>
                <!-- .nk-block-head-content -->
                <div class="nk-block-head-content">
                  <div class="toggle-wrap nk-block-tools-toggle">
                    <a
                      href="#"
                      class="btn btn-icon btn-trigger toggle-expand me-n1"
                      data-target="pageMenu"
                      ><em class="icon ni ni-more-v"></em
                    ></a>
                    <div class="toggle-expand-content" data-content="pageMenu">
                      <ul class="nk-block-tools g-3">
                        <li>
                          <a
                            href="'.site_url('Setup/add_customer').'"
                            data-original-title="Add New Request"
                            data-toggle="tooltip"
                            data-placement="bottom"
                            data-bs-target="#modalForm"
                            class="btn btn-round btn-sm btn-primary"
                          >
                            <em class="icon ni ni-plus"></em>
                          </a>
                        </li>                        
                      </ul>
                    </div>
                  </div>
                </div>
                <!-- .nk-block-head-content -->
              </div>
            </div>
            <!-- .nk-block-head -->
            <div class="nk-block nk-block-lg">
              <div class="card card-bordered card-preview">
                <div class="card-inner">
                  <table
                    id="example"
                    class="nk-tb-list nk-tb-ulist nowrap table cell-border"
                    style="width: 100%"
                    data-export-title="Export"
                  >
                    <thead>
                      <tr class="nk-tb-item nk-tb-head">
                      <th>Customer Name</th>
                        <th>Customer Code</th>
                        <th>Customer Type</th>
                        <th>Credit Type</th>
                        <th>Credit Limit</th>
                        <th></th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>                  
                </div>
              </div>
              <!-- .card-preview -->
            </div>
            <!-- nk-block -->
          </div>
          <!-- .components-preview -->
        </div>
      </div>
    </div>
  </div>
</section>
'; ?>