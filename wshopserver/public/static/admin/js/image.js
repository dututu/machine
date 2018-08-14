// $(function() {
//     $("#file_upload").uploadifive({
//         'uploadScript'        : SCOPE.image_upload,
//         'buttonText'      : '选择图片',
//         'fileTypeDesc'    : 'Image files',
//         'fileObjName'     : 'file',
//         'fileTypeExts'    : '*.gif; *.jpg; *.png',
//         'onUploadComplete' : function(file, data, response) {
//             console.log(file);
//             console.log(data);
//             console.log(response);
//             if(response) {
//                 var obj = eval('(' + data + ')');
//                 // var obj = JSON.parse(obj2);
//                 $("#upload_org_code_img").attr("src", obj.data);
//                 $("#file_upload_image").attr("value", obj.data);
//                 $("#upload_org_code_img").show();
//             }
//         }
//     });
//     $("#file_upload2").uploadifive({
//         'uploadScript'        : SCOPE.image_upload,
//         'buttonText'      : '选择图片',
//         'fileTypeDesc'    : 'Image files',
//         'fileObjName'     : 'file',
//         'fileTypeExts'    : '*.gif; *.jpg; *.png',
//         'onUploadComplete' : function(file, data, response) {
//             console.log(file);
//             console.log(data);
//             console.log(response);
//             if(response) {
//                 var obj = eval('(' + data + ')');
//                 // var obj = JSON.parse(obj2);
//                 $("#upload_org_code_img2").attr("src", obj.data);
//                 $("#file_upload_image2").attr("value", obj.data);
//                 $("#upload_org_code_img2").show();
//             }
//         }
//     });
//     $("#file_upload_other").uploadifive({
//         'uploadScript'        : SCOPE.image_upload,
//         'buttonText'      : '选择图片',
//         'fileTypeDesc'    : 'Image files',
//         'fileObjName'     : 'file',
//         'fileTypeExts'    : '*.gif; *.jpg; *.png',
//         'onUploadComplete' : function(file, data, response) {
//             console.log(file);
//             console.log(data);
//             console.log(response);
//             if(response) {
//                 var obj = JSON.parse(data);
//                 $("#upload_org_code_img_other").attr("src", obj.data);
//                 $("#file_upload_image_other").attr("value", obj.data);
//                 $("#upload_org_code_img_other").show();
//             }
//         }
//     });
// });