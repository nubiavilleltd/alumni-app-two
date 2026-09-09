<?php
// Generic message (informational)
if ($this->session->flashdata('message')) {
        echo '<div class="alert alert-info alert-dismissible">'
                . '<button type="button" class="close" data-bs-dismiss="alert"></button>'
                . $this->session->flashdata('message')
                . '</div>';
}

// Success message
if ($this->session->flashdata('success')) {
        echo '<div class="alert alert-success alert-dismissible">'
                . '<button type="button" class="close" data-bs-dismiss="alert"></button>'
                . $this->session->flashdata('success')
                . '</div>';
}

// Error message
if ($this->session->flashdata('error')) {
        echo '<div class="alert alert-danger alert-dismissible">'
                . '<button type="button" class="close" data-bs-dismiss="alert">&times;</button>'
                . $this->session->flashdata('error')
                . '</div>';
}

// Validation errors
if (validation_errors()) {
        echo '<div class="alert alert-warning alert-dismissible">'
                . '<button type="button" class="close" data-bs-dismiss="alert">&times;</button>'
                . validation_errors()
                . '</div>';
}
?>