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
                  <h4 class="nk-block-title">'.$page_title.'</h4>
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
                            href="javascript:add_row()"
                            data-original-title="Add New Parameter"
                            data-toggle="tooltip"
                            data-placement="bottom"
                            data-bs-target="#modalForm"
                            class="btn btn-round btn-sm btn-primary"
                          >
                            <em class="icon ni ni-plus"></em>
                          </a>
                        </li>
                        <li>
                          <a
                            href="javascript:delete_row()"
                            data-toggle="tooltip"
                            data-placement="bottom"
                            data-original-title="Delete Selected"
                            class="btn btn-round btn-sm btn-primary"
                          >
                            <em class="icon ni ni-trash"></em>
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
                    style="width:100%"
                    class="nk-tb-list nk-tb-ulist nowrap table cell-border"
                    data-export-title="Export"
                  >
                    <thead>
                      <tr class="nk-tb-item nk-tb-head">
                        <th>REF</th>
                        <th>Parameters</th>
                        <th>Setup Value</th>
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

  <!-- Modal -->
  <div
    class="modal fade"
    id="modal_form"
    tabindex="-1"
    role="dialog"
    aria-labelledby="myModalLabel"
    aria-hidden="true"
  >
    <div class="modal-dialog">
      <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
          <h5 class="modal-title">Modal title</h5>
          <a href="#" class="close" data-bs-dismiss="modal" aria-label="Close">
            <em class="icon ni ni-cross"></em>
          </a>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
          <form action="#" id="form" class="form-horizontal">
            <input
              type="hidden"
              name="'.$this->security->get_csrf_token_name()
                          .'"
              value="'.$this->security->get_csrf_hash().'"
            />
            <input type="hidden" value="" name="id" />
            <div class="form-group">
              <label class="form-label">Setup Parameters:</label>
              <div class="form-control-wrap">
                <input
                  name="setup_name"
                  type="text"
                  class="form-control"
                  placeholder="Enter Setup Parameters"
                />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Setup Vaues:</label>
              <div class="form-control-wrap">
                <textarea
                  name="setup_value"
                  cols="40"
                  class="form-control"
                  placeholder="Enter Setup Values"
                ></textarea>
              </div>
            </div>
          </form>
        </div>
        <!-- Modal Footer -->
        <div class="modal-footer">
          <button
            type="button"
            id="btnSave"
            onclick="save()"
            class="btn btn-primary"
          >
            Save
          </button>
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</section>
'; ?>