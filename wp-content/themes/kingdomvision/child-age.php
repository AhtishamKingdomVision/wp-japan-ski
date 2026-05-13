<?php
echo '<section class="child_age">';
	echo '<div class="ch_inn">';
		echo '<a href="javascript:void(0)" class="child_close"><i class="fa fa-close"></i></a>';
		echo '<ul>';
			echo '<li>';
				// echo '<label>Child 1 age*</label>';
                echo '<select data-child="child_1" required>';
                echo '<option  selected value="0">Child 1 age*</option>';
                for($i=1;$i<=15;$i++){
                    echo '<option value="'.$i.'">'.$i.'</option>';
                }
                echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 2 age*</label>';
				echo '<select data-child="child_2" required>';
				echo '<option  selected value="0">Child 2 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 3 age*</label>';
				echo '<select data-child="child_3" required>';
				echo '<option  selected value="0">Child 3 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 4 age*</label>';
				echo '<select data-child="child_4" required>';
				echo '<option  selected value="0">Child 4 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 5 age*</label>';
				echo '<select data-child="child_5" required>';
				echo '<option  selected value="0">Child 5 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 6 age*</label>';
				echo '<select data-child="child_6" required>';
				echo '<option  selected value="0">Child 6 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 7 age*</label>';
				echo '<select data-child="child_7" required>';
				echo '<option  selected value="0">Child 7 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 8 age*</label>';
				echo '<select data-child="child_8" required>';
				echo '<option  selected value="0">Child 8 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 9 age*</label>';
				echo '<select data-child="child_9" required>';
				echo '<option  selected value="0">Child 9 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 10 age*</label>';
				echo '<select data-child="child_10" required>';
				echo '<option  selected value="0">Child 10 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 11 age*</label>';
				echo '<select data-child="child_11" required>';
				echo '<option  selected value="0">Child 11 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 12 age*</label>';
				echo '<select data-child="child_12" required>';
				echo '<option  selected value="0">Child 12 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 13 age*</label>';
				echo '<select data-child="child_13" required>';
				echo '<option  selected value="0">Child 13 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 14 age*</label>';
				echo '<select data-child="child_14" required>';
				echo '<option  selected value="0">Child 14 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
			echo '<li>';
				// echo '<label>Child 15 age*</label>';
				echo '<select data-child="child_15" required>';
				echo '<option  selected value="0">Child 15 age*</option>';
				for($i=1;$i<=15;$i++){
				    echo '<option value="'.$i.'">'.$i.'</option>';
				}
				echo '</select>';
			echo '</li>';
		echo '</ul>';
        echo '<div class="age-error-box" style="display:none;">';
           echo 'All Fields Are Required';
        echo '</div>';
		echo '<a class="btn light_green age_confirm" href="javascript:;">DONE</a>';
	echo '</div>'; //ch_inn
echo '</section>'; //child_age