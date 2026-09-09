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
                            Add
                          </a>
                        </li>
                        <li>
                          <a
                            href="javascript:reset_row()"
                            data-toggle="tooltip"
                            data-placement="bottom"
                            data-original-title="Reset Selected"
                            class="btn btn-round btn-sm btn-primary"
                          >
                            <span>Reset</span>
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
                    style="width: 100%"
                    class="nk-tb-list nk-tb-ulist nowrap table cell-border"
                    data-export-title="Export"
                  >
                    <thead>
                      <tr class="nk-tb-item nk-tb-head">
                        <th>REF</th>
                        <th>Email</th>
                        <th>Fullname</th>
                        <th>Designation</th>
                        <th>Dept</th>
                        <th>Manager</th>
                        <th>Manager Email</th>
                        <th>User Area</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                  <hr class="preview-hr" />
                  <div class="preview-title-lg overline-title">
                    Import User List
                  </div>
                  <hr class="preview-hr" />
                  '.form_open_multipart('Setup/import/users',array('name'=>'update_form','class'=>'form-horizontal')).'
                  <div class="row">
                    <div class="offset-lg-1 col-lg-2 mt-1">
                      Select users list to import:
                    </div>
                    <div class="col-lg-8">
                      <div class="form-control-wrap">
                        <div class="input-group">
                          <div class="form-file">
                            <input
                              type="file"
                              name="userfile"
                              class="form-file-input"
                              id="inputGroupFile04"
                            />
                            <label
                              class="form-file-label"
                              for="inputGroupFile04"
                              >Choose file</label
                            >
                          </div>
                          <div class="input-group-append">
                            <button
                              type="submit"
                              name="login"
                              class="btn btn-outline-primary btn-dim"
                            >
                              Import
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  '.form_close().'
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
  <form action="#" id="form">
    <div
      class="modal fade"
      id="modal_form"
      tabindex="-1"
      role="dialog"
      aria-labelledby="myModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <!-- Modal Header -->
          <div class="modal-header">
            <h5 class="modal-title">Modal title</h5>
            <a
              href="#"
              class="close"
              data-bs-dismiss="modal"
              aria-label="Close"
            >
              <em class="icon ni ni-cross"></em>
            </a>
          </div>

          <!-- Modal Body -->
          <div class="modal-body form-validate is-alter row">
            <input
              type="hidden"
              name="'.$this->security->get_csrf_token_name()
                        .'"
              value="'.$this->security->get_csrf_hash().'"
            />
            <input type="hidden" value="" name="id" />
            <input type="hidden" value="" name="manager" id="manager" />
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Full Name:</label>
                <div class="form-control-wrap">
                  <input
                    name="fullname"
                    type="text"
                    class="form-control"
                    placeholder="Enter Full Name"
                  />
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Email:</label>
                <div class="form-control-wrap">
                  <input
                    type="text"
                    name="email"
                    class="form-control"
                    placeholder="Enter Email"
                  />
                </div>
              </div>
            </div>
            <div class="col-md-6 hide_this">
              <div class="form-group">
                <label class="form-label">Confirm:</label>
                <div class="form-control-wrap">
                  <input
                    type="text"
                    name="email2"
                    class="form-control"
                    placeholder="Confirm Email"
                  />
                </div>
              </div>
            </div>
            <div class="col-md-6 hide_this">
              <div class="form-group">
                <label class="form-label">Password:</label>
                <div class="form-control-wrap">
                  <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter Password"
                  />
                </div>
              </div>
            </div>
            <div class="col-md-6 hide_this">
              <div class="form-group">
                <label class="form-label">Confirm:</label>
                <div class="form-control-wrap">
                  <input
                    type="password"
                    name="repass"
                    class="form-control"
                    placeholder="Re-enter Password"
                  />
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Department:</label>
                <div class="form-control-wrap">'.$dept.'</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Designation:</label>
                <div class="form-control-wrap">
                  <input
                    type="text"
                    name="designation"
                    class="form-control"
                    placeholder="Enter Job Designation"
                  />
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">User Area:</label>
                <div class="form-control-wrap">'.$userArea.'</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Customer Name:</label>
                <div class="form-control-wrap">
                  <input
                    type="text"
                    name="cust_name"
                    class="form-control"
                    placeholder="Customer Name"
                  />
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Manager:</label>
                <div class="form-control-wrap">'.$mgr_email.'</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Customer Code:</label>
                <div class="form-control-wrap">
                  <input
                    type="text"
                    name="cust_code"
                    class="form-control"
                    placeholder="Customer Code"
                  />
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Status:</label>
                <div class="g-4 align-center flex-wrap">
                  <div class="g">
                    <div class="custom-control custom-control-sm custom-radio">
                      <input
                        type="radio"
                        class="custom-control-input"
                        name="user_status"
                        id="Active"
                        value="Active"
                      />
                      <label class="custom-control-label" for="Active"
                        >Active</label
                      >
                    </div>
                  </div>
                  <div class="g">
                    <div class="custom-control custom-control-sm custom-radio">
                      <input
                        type="radio"
                        class="custom-control-input"
                        name="user_status"
                        id="Inactive"
                        value="Inactive"
                      />
                      <label class="custom-control-label" for="Inactive"
                        >Inactive</label
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">User Role</label>
                <div class="form-control-wrap">'.$user_role.'</div>
              </div>
            </div>
            &nbsp;
            <div class="col-md-12"></div>
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
            <button
              type="button"
              class="btn btn-danger"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</section>
'; ?>
